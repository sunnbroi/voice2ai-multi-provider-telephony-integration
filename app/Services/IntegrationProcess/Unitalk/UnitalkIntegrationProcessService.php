<?php

namespace App\Services\IntegrationProcess\Unitalk;

use App\DTO\IntegrationProcess\IntegrationProcessDownloadStatusDTO;
use App\DTO\IntegrationProcess\IntegrationProcessResultDTO;
use App\Models\Call;
use App\Models\Integration;
use App\Services\IntegrationProcess\IntegrationProcessService;
use App\Services\Notification\NotificationService;
use App\Services\OpenAi\OpenAIService;
use App\Services\Telegram\TelegramService;
use App\Services\Unitalk\UnitalkService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Throwable;

class UnitalkIntegrationProcessService extends IntegrationProcessService
{
    protected UnitalkService $unitalkService;

    public function __construct(
        UnitalkService $unitalkService,
        OpenAIService $openAIService,
        TelegramService $telegramService,
        NotificationService $notificationService
    ) {
        parent::__construct($openAIService, $telegramService, $notificationService);
        $this->unitalkService = $unitalkService;
    }

    public function processIntegration(Integration $integration): ?IntegrationProcessResultDTO
    {
        Log::info("UnitalkIntegrationProcessService processIntegration $integration->id started");

        $since = (int)($integration->prev_timestamp ?? now('Europe/Kyiv')->subHour()->timestamp);
        $from = gmdate('Y-m-d H:i:s', $since);
        $to = Carbon::now('Europe/Kyiv')->format('Y-m-d H:i:s');

        $resultDto = new IntegrationProcessResultDTO();
        $resultDto->integrationId = $integration->id;
        $resultDto->from = $from;
        $resultDto->to = $to;

        try {
            $callsHistory = $this->unitalkService->getCallsHistory($integration, $from, $to);
            $count = (int)($callsHistory['count'] ?? 0);
            $resultDto->callsCount = $count;
            Log::info("Unitalk calls history loaded for integration $integration->id | count: $count");

            if ($count) {
                $calls = $callsHistory['calls'] ?? [];
                foreach ($calls as $callStat) {
                    $call = $this->processIntegrationPbxCallStat($integration, $callStat);

                    Log::info("Debug: ", [
                        'chat_id' => $integration->telegram_chat_id,
                        'was_created' => $call->wasRecentlyCreated,
                        'status' => $call->status,
                        'recording' => $call->recording_url,
                        'recording_status' => $call->recording_status,
                    ]);

                    $shouldNotify = match ($integration->notify_type) {
                        'all' => true,
                        'missed' => $call->status === 'missed',
                        default => false,
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
                    }
                }
            }
        } catch (Exception $e) {
            Log::error('Unitalk stats request failed for integration ' . $integration->id . ': ' . $e->getMessage());
            $integration->active = false;
            $integration->save();
            $this->notificationService->notifyAdminIntegrationDisabled($integration, $e);
        }

        return $resultDto;
    }

    private function processIntegrationPbxCallStat(Integration $integration, array $callStat): ?Call
    {
        if (!!$callStat) {
            try {
                $callTime = isset($callStat['date']) ? strtotime($callStat['date']) : null;
                $id = $callStat['id'] ?? null;
                $direction = $callStat['direction'] === "IN" ? 'in' : 'out';

                $outerNumber = $callStat['outerNumber'] ?? null;
                $callFrom = $callStat['from'] ?? null;
                $callTo = count($callStat['to']) > 0 ? $callStat['to'][0] : null;
                if ($direction === 'in') {
                    $callTo = $outerNumber;
                } elseif ($direction === 'out') {
                    $callFrom = $outerNumber;
                }

                $status = $callStat['state'] == "ANSWER" ? 'answered' : 'missed';
                $duration = (int)($callStat['secondsTalk'] ?? 0);
                $operator = $callStat['lastGroupName'] ?? null;
                $recordingDownloadStatus = $this->downloadAndSaveUnitalkRecord($callStat);

                $call = Call::updateOrCreate([
                    'integration_id' => $integration->id,
                    'external_call_id' => $id,
                ], [
                    'call_time' => now()->setTimestamp($callTime),
                    'from_phone' => $callFrom,
                    'to_phone' => $callTo,
                    'direction' => $direction,
                    'status' => $status,
                    'duration' => $duration,
                    'operator_name' => $operator,
                    'recording_url' => $recordingDownloadStatus->recording,
                    'recording_status' => $recordingDownloadStatus->recordingStatus,
                ]);

                return $call;
            } catch (Exception $e) {
                Log::error("Zadarma stat proc failed for integration $integration->id : {$e->getMessage()}");
            }
        }

        return null;
    }

    private function downloadAndSaveUnitalkRecord(array $callStat): IntegrationProcessDownloadStatusDTO
    {
        if (!!$callStat && isset($callStat['link'])) {
            $id = $callStat['id'] ?? null;
            $link = $callStat['link'];

            try {
                $fileName = "recordings/unitalk_$id.mp3";
                $recording = $this->downloadAndSaveRecord($id, $fileName, $link);
                if (!!$recording) {
                    return new IntegrationProcessDownloadStatusDTO($recording, 'uploaded');
                }
            } catch (Throwable $e) {
                Log::error("Ошибка при скачивании записи Zadarma: " . $e->getMessage());
            }
        }

        return new IntegrationProcessDownloadStatusDTO(null, null);
    }
}
