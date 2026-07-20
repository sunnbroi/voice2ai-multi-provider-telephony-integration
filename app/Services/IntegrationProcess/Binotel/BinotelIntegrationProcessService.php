<?php

namespace App\Services\IntegrationProcess\Binotel;

use App\DTO\IntegrationProcess\IntegrationProcessResultDTO;
use App\Models\Call;
use App\Models\Integration;
use App\Services\IntegrationProcess\IntegrationProcessService;
use App\Services\Notification\NotificationService;
use App\Services\OpenAi\OpenAIService;
use App\Services\Telegram\TelegramService;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BinotelIntegrationProcessService extends IntegrationProcessService
{
    private ?string $proxy;

    public function __construct(
        OpenAIService $openAIService,
        TelegramService $telegramService,
        NotificationService $notificationService
    ) {
        parent::__construct($openAIService, $telegramService, $notificationService);
        $this->proxy = null;
    }

    /**
     * @inheritDoc
     */
    public function processIntegration(Integration $integration): ?IntegrationProcessResultDTO
    {
        $resultDto = new IntegrationProcessResultDTO();
        $resultDto->integrationId = $integration->id;

        if ($integration->provider_id == null) {
            return $resultDto;
        }

        // $timestamp = now()->subDays(2)->startOfDay()->timestamp;
        $timestamp = now()->subHours(4)->timestamp;
        if ($integration->prev_timestamp != null && $integration->prev_timestamp > $timestamp) {
            $timestamp = $integration->prev_timestamp;
        }

        $payload = [
            'timestamp' => $timestamp,
            'key' => $integration->api_key,
            'secret' => $integration->secret,
        ];

        try {
            if (is_null($this->proxy)) {
                $incoming = Http::post('https://api.binotel.com/api/4.0/stats/all-incoming-calls-since.json', $payload);
                $outgoing = Http::post('https://api.binotel.com/api/4.0/stats/all-outgoing-calls-since.json', $payload);
            } else {
                $incoming = Http::withOptions(['proxy' => $this->proxy])->post('https://api.binotel.com/api/4.0/stats/all-incoming-calls-since.json', $payload);
                $outgoing = Http::withOptions(['proxy' => $this->proxy])->post('https://api.binotel.com/api/4.0/stats/all-outgoing-calls-since.json', $payload);
            }
        } catch (Exception $e) {
            Log::error('Failed to fetch calls for integration ' . $integration->id);
            $integration->active = false;
            $integration->save();
            $this->notificationService->notifyAdminIntegrationDisabled($integration, $e);
        }

        if (($incoming->json()['status'] ?? null) !== 'success' || ($outgoing->json()['status'] ?? null) !== 'success') {
            Log::error('Failed to fetch calls for integration ' . $integration->id);
            $integration->active = false;
            $integration->save();
            return $resultDto;
        }

        $allCalls = array_merge(
            $incoming->json('callDetails', []),
            $outgoing->json('callDetails', [])
        );

        $processedTimestamps = [];
        $minPendingTimestamp = null;

        foreach ($allCalls as $data) {
            $callTime = (int)($data['startTime'] ?? 0);

            $disposition = strtolower($data['disposition'] ?? '');

            if (in_array($disposition, ['online', 'calling'])) {
                if (is_null($minPendingTimestamp) || $callTime < $minPendingTimestamp) {
                    $minPendingTimestamp = $callTime;
                }
                continue;
            }

            $callId = $data['generalCallID'] ?? null;
            $direction = ($data['callType'] ?? '0') === '0' ? 'in' : 'out';
            $status = $disposition === 'answer' ? 'answered' : 'missed';
            $operator = $data['employeeData']['name'] ?? null;
            $from = $direction === 'in' ? $data['externalNumber'] : $data['pbxNumberData']['number'] ?? null;
            $to = $direction === 'in' ? $data['pbxNumberData']['number'] ?? null : $data['externalNumber'];
            $duration = (int)($data['billsec'] ?? 0);

            $recording = null;
            $recordingStatus = $data['recordingStatus'] ?? null;

            if ($recordingStatus === 'uploading') {
                Log::info("Звонок {$callId} в статусе uploading, отложен до следующей итерации");
                return $resultDto; // просто сохраняем, но не обновляем prev_timestamp
            }

            if ($recordingStatus === 'uploaded') {
                Log::info("Звонок {$callId} в статусе uploaded, скачиваю");

                if (is_null($this->proxy)) {
                    $recResp = Http::post('https://api.binotel.com/api/4.0/stats/call-record.json', [
                        'generalCallID' => $callId,
                        'key' => $integration->api_key,
                        'secret' => $integration->secret,
                    ]);
                } else {
                    $recResp = Http::withOptions(['proxy' => $this->proxy])->post('https://api.binotel.com/api/4.0/stats/call-record.json', [
                        'generalCallID' => $callId,
                        'key' => $integration->api_key,
                        'secret' => $integration->secret,
                    ]);
                }

                if ($recResp->ok() && isset($recResp->json()['url'])) {
                    $fileUrl = $recResp->json()['url'];
                    $fileName = "recordings/{$callId}.mp3";

                    try {
                        Log::info("Звонок {$callId} fileName=$fileName | fileUrl=$fileUrl");
                        $fileContent = file_get_contents($fileUrl);
                        Storage::disk('public')->put($fileName, $fileContent);
                        $recording = Storage::url($fileName);
                    } catch (\Throwable $e) {
                        Log::error("Ошибка при скачивании файла {$callId}: " . $e->getMessage());
                    }
                }
            }

            if (
                Call::where('integration_id', $integration->id)
                ->where('external_call_id', $callId)
                ->exists()
            ) {
                continue;
            }

            $call = Call::updateOrCreate([
                'integration_id' => $integration->id,
                'external_call_id' => $callId,
            ], [
                'call_time' => now()->setTimestamp($callTime),
                'from_phone' => $from,
                'to_phone' => $to,
                'direction' => $direction,
                'status' => $status,
                'duration' => $duration,
                'operator_name' => $operator,
                'recording_url' => $recording,
                'recording_status' => $recordingStatus,
            ]);

            Log::info("Debug: ", [
                'chat_id' => $integration->telegram_chat_id,
                'was_created' => $call->wasRecentlyCreated,
                'status' => $status,
                'recording' => $recording,
                'recording_status' => $recordingStatus,
            ]);

            $shouldNotify = match ($integration->notify_type) {
                'all' => true,
                'missed' => $status === 'missed',
                default => false,
            };

            if (
                $shouldNotify &&
                $integration->telegram_chat_id &&
                $call->wasRecentlyCreated &&
                (
                    $status === 'missed' || // для missed звонков — запись не обязательна
                    ($status === 'answered' && $recording && $recordingStatus === 'uploaded')
                )
            ) {
                Log::info("Отправляем звонок $call->id в очередь для обработки");
                $this->proccessCall($call);
            }

            $processedTimestamps[] = $callTime;
        }

        $newTimestamp = $minPendingTimestamp ?? (!empty($processedTimestamps) ? max($processedTimestamps) + 1 : null);

        if ($newTimestamp) {
            $integration->update(['prev_timestamp' => $newTimestamp]);
            Log::info("Обновлен prev_timestamp для интеграции {$integration->id} -> {$newTimestamp}");
        }

        return $resultDto;
    }

    /**
     * @inheritDoc
     */
    public function processPendingRecordings(int $limit = 100)
    {
        Call::where('recording_status', 'uploading')
            ->whereNotNull('integration_id')
            ->whereHas('integration', function ($q) {
                $q->active()->binotel();
            })
            ->with('integration')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->each(function ($call) {
                $integration = $call->integration;
                if (!$integration) {
                    return;
                }

                Log::info("Проверка записи для звонка {$call->id}");

                if (is_null($this->proxy)) {
                    $resp = Http::post('https://api.binotel.com/api/4.0/stats/call-record.json', [
                        'generalCallID' => $call->external_call_id,
                        'key' => $integration->api_key,
                        'secret' => $integration->secret,
                    ]);
                } else {
                    $resp = Http::withOptions(['proxy' => $this->proxy])->post('https://api.binotel.com/api/4.0/stats/call-record.json', [
                        'generalCallID' => $call->external_call_id,
                        'key' => $integration->api_key,
                        'secret' => $integration->secret,
                    ]);
                }

                if ($resp->ok() && isset($resp->json()['url'])) {
                    try {
                        $fileName = "recordings/{$call->external_call_id}.mp3";
                        Storage::disk('public')->put($fileName, file_get_contents($resp['url']));
                        $call->update([
                            'recording_url' => Storage::url($fileName),
                            'recording_status' => 'uploaded',
                        ]);
                        Log::info("Успешно загружена запись для звонка {$call->id}");
                    } catch (Exception $e) {
                        Log::error("Ошибка при загрузке записи для {$call->id}: {$e->getMessage()}");
                    }
                } else {
                    Log::info("Запись для звонка {$call->id} всё ещё недоступна");
                }
            });
    }
}
