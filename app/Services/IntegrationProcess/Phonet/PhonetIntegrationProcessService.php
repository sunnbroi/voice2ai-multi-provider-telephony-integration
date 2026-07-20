<?php

namespace App\Services\IntegrationProcess\Phonet;

use App\DTO\IntegrationProcess\IntegrationProcessDownloadStatusDTO;
use App\DTO\IntegrationProcess\IntegrationProcessResultDTO;
use App\Models\Call;
use App\Models\Integration;
use App\Services\IntegrationProcess\IntegrationProcessService;
use App\Services\Notification\NotificationService;
use App\Services\OpenAi\OpenAIService;
use App\Services\Phonet\PhonetService;
use App\Services\Telegram\TelegramService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PhonetIntegrationProcessService extends IntegrationProcessService
{
    protected PhonetService $phonetService;

    public function __construct(
        PhonetService $phonetService,
        OpenAIService $openAIService,
        TelegramService $telegramService,
        NotificationService $notificationService
    ) {
        parent::__construct($openAIService, $telegramService, $notificationService);
        $this->phonetService = $phonetService;
    }

    /**
     * Основной цикл интеграции Phonet (аналог Unitalk).
     */
    public function processIntegration(Integration $integration): ?IntegrationProcessResultDTO
    {
        Log::info("PhonetIntegrationProcessService processIntegration {$integration->id} started");
        Log::info("PhonetIntegrationProcessService processIntegration {$integration->id} started", [
        'prev_timestamp_raw' => $integration->prev_timestamp,
        'prev_timestamp_cast' => (int)($integration->prev_timestamp ?? 0),
        ]);

        // Окно времени — как в Unitalk: prev_timestamp -> from, now(Kyiv) -> to
        $since = (int)($integration->prev_timestamp ?? now('Europe/Kyiv')->subHour()->timestamp);
        $from  = gmdate('Y-m-d H:i:s', $since);
        $to    = Carbon::now('Europe/Kyiv')->format('Y-m-d H:i:s');

        // Для Phonet API нужны миллисекунды UTC
        $fromMs = $since * 1000;
        $toMs   = Carbon::now('UTC')->getTimestampMs();

        $result = new IntegrationProcessResultDTO();
        $result->integrationId = $integration->id;
        $result->from          = $from;
        $result->to            = $to;
        $result->callsCount    = 0;
        $result->created       = 0;
        $result->updated       = 0;
        $result->notified      = 0;

        try {
            // История звонков (2=out, 4=in)
            $history = $this->phonetService->getCallsHistory($integration, $fromMs, $toMs, [2, 4]);
            $count   = (int)($history['count'] ?? 0);
            $result->callsCount = $count;

            Log::info("Phonet calls history loaded for integration {$integration->id} | count: {$count}");

            if ($count > 0) {
                $calls = $history['calls'] ?? [];

                foreach ($calls as $callStat) {
                    $call = $this->processIntegrationPbxCallStat($integration, $callStat);
                    if (!$call) {
                        continue;
                    }

                    $call->wasRecentlyCreated ? $result->created++ : $result->updated++;

                    Log::info("Phonet call processed", [
                        'integration_id'   => $integration->id,
                        'was_created'      => $call->wasRecentlyCreated,
                        'status'           => $call->status,
                        'recording'        => $call->recording_url,
                        'recording_status' => $call->recording_status,
                    ]);

                    // Нотификации — как в Unitalk
                    $shouldNotify = match ($integration->notify_type) {
                        'all'    => true,
                        'missed' => $call->status === 'missed',
                        default  => false,
                    };

                    if (
                        $shouldNotify &&
                        $integration->telegram_chat_id &&
                        $call->wasRecentlyCreated &&
                        (
                            $call->status === 'missed' ||
                            ($call->status === 'answered' && $call->recording_url && $call->recording_status === 'uploaded')
                        )
                    ) {
                        $this->proccessCall($call);
                        $result->notified++;
                    }
                }
            }

            // Сдвигаем маркер и сохраняем
            $integration->prev_timestamp = Carbon::now('Europe/Kyiv')->timestamp;
            $integration->save();

        } catch (\Throwable $e) {
            Log::error("Phonet stats request failed for integration {$integration->id}: {$e->getMessage()}");
            $integration->active = false;
            $integration->save();
            $this->notificationService->notifyAdminIntegrationDisabled($integration, $e);
        }

        return $result;
    }

    /**
     * Обработка одного элемента истории Phonet -> upsert Call.
     */
    private function processIntegrationPbxCallStat(Integration $integration, array $callStat): ?Call
    {
        if (empty($callStat)) {
            return null;
        }

        try {
            // Идентификатор и направление
            $uuid        = (string)($callStat['uuid'] ?? '');
            $lgDirection = (int)($callStat['lgDirection'] ?? 0); // 2=out, 4=in

            if ($uuid === '' || !in_array($lgDirection, [2, 4], true)) {
                return null; // пропускаем внутренние/пустые
            }

            $direction = $lgDirection === 4 ? 'in' : 'out';

            // Время звонка (мс -> сек)
            $endAtMs   = $callStat['endAt'] ?? null;
            $dialAtMs  = $callStat['dialAt'] ?? null;
            $callTs    = is_numeric($endAtMs) ? (int) floor(((int)$endAtMs) / 1000)
                        : (is_numeric($dialAtMs) ? (int) floor(((int)$dialAtMs) / 1000) : null);

            // Номера
            $otherLegNum = isset($callStat['otherLegNum']) ? preg_replace('/[^\d\+]/', '', (string)$callStat['otherLegNum']) : null;
            $ext         = isset($callStat['leg']['ext'])  ? preg_replace('/[^\d\+]/', '', (string)$callStat['leg']['ext'])  : null;

            $callFrom = $direction === 'in' ? $otherLegNum : $ext;
            $callTo   = $direction === 'in' ? $ext         : $otherLegNum;

            // Статус/длительность
            $disposition = (int)($callStat['disposition'] ?? 0); // 0=answered, 1..4=missed
            $status      = $disposition === 0 ? 'answered' : 'missed';

            $duration = (int) floor((float)($callStat['billSecs'] ?? $callStat['duration'] ?? 0));
            $operator = (string) (data_get($callStat, 'leg.displayName') ?? '');

            // Запись разговора
            $downloadStatus = new IntegrationProcessDownloadStatusDTO(null, null);
            if ($status === 'answered') {
                $downloadStatus = $this->downloadAndSavePhonetRecord($integration, $callStat);
            }

            // Upsert Call
            /** @var Call $call */
            $call = Call::updateOrCreate([
                'integration_id'   => $integration->id,
                'external_call_id' => $uuid,
            ], [
                'call_time'        => $callTs ? now()->setTimestamp($callTs) : null,
                'from_phone'       => $callFrom ?: null,
                'to_phone'         => $callTo ?: null,
                'direction'        => $direction,
                'status'           => $status,
                'duration'         => $duration,
                'operator_name'    => $operator ?: null,
                'recording_url'    => $downloadStatus->recording,
                'recording_status' => $downloadStatus->recordingStatus,
            ]);

            return $call;

        } catch (\Throwable $e) {
            Log::error("Phonet stat proc failed for integration {$integration->id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Скачивает запись через базовый метод абстракции и возвращает статус.
     * Учитывает, что host Phonet может лежать в secret.
     */
    private function downloadAndSavePhonetRecord(Integration $integration, array $callStat): IntegrationProcessDownloadStatusDTO
    {
        $uuid = $callStat['uuid'] ?? null;
        if (!$uuid) {
            return new IntegrationProcessDownloadStatusDTO(null, null);
        }

        // Прямая ссылка, если есть; иначе — публичная по uuid и host
        $link = (string)($callStat['audioRecUrl'] ?? '');

        if ($link === '') {
            // host = domain || secret
            $host = trim((string)($integration->domain ?: $integration->secret ?: ''));
            if ($host !== '') {
                $host = preg_replace('#^https?://#i', '', $host);
                $host = rtrim($host, '/');
                $link = "https://{$host}/rest/public/calls/{$uuid}/audio";
            }
        }

        if ($link === '') {
            return new IntegrationProcessDownloadStatusDTO(null, null);
        }

        try {
            $fileName = "recordings/phonet_{$uuid}.mp3";
            $recording = $this->downloadAndSaveRecord((string)$uuid, $fileName, $link);
            if (!empty($recording)) {
                return new IntegrationProcessDownloadStatusDTO($recording, 'uploaded');
            }
        } catch (\Throwable $e) {
            Log::error("Ошибка при скачивании записи Phonet: " . $e->getMessage());
        }

        return new IntegrationProcessDownloadStatusDTO(null, null);
    }
}
