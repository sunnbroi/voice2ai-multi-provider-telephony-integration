<?php

namespace App\Services\IntegrationProcess\Zadarma;

use App\DTO\IntegrationProcess\IntegrationProcessDownloadStatusDTO;
use App\DTO\IntegrationProcess\IntegrationProcessResultDTO;
use App\Models\Call;
use App\Models\Integration;
use App\Services\IntegrationProcess\IntegrationProcessService;
use App\Services\Notification\NotificationService;
use App\Services\OpenAi\OpenAIService;
use App\Services\Telegram\TelegramService;
use App\Services\Zadarma\ZadarmaService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Сервис для интеграции с Zadarma
 */
class ZadarmaIntegrationProcessService extends IntegrationProcessService
{
    protected ZadarmaService $zadarmaService;

    public function __construct(
        ZadarmaService $zadarmaService,
        OpenAIService $openAIService,
        TelegramService $telegramService,
        NotificationService $notificationService
    ) {
        parent::__construct($openAIService, $telegramService, $notificationService);
        $this->zadarmaService = $zadarmaService;
    }

    /**
     * @inheritDoc
     */
    public function processIntegration(Integration $integration): ?IntegrationProcessResultDTO
    {
        Log::info("ZadarmaIntegrationProcessService processIntegration $integration->id started");

        $since = (int)($integration->prev_timestamp ?? now('Europe/Kyiv')->subHour()->timestamp);
        $from = gmdate('Y-m-d H:i:s', $since);
        $to = Carbon::now('Europe/Kyiv')->format('Y-m-d H:i:s');

        $resultDto = new IntegrationProcessResultDTO();
        $resultDto->integrationId = $integration->id;
        $resultDto->from = $from;
        $resultDto->to = $to;

        try {
            $pbxStats = $this->zadarmaService->getPbxStatistics($integration, $from, $to);
            if (!!$pbxStats && !!$pbxStats->stats) {
                $resultDto->callsCount = count($pbxStats->stats);

                foreach ($pbxStats->stats as $callStat) {
                    $call = $this->processIntegrationPbxCallStat($integration, $callStat);
                    if ($call == null) {
                        continue;
                    }

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
            Log::error('Zadarma stats request failed for integration ' . $integration->id . ': ' . $e->getMessage());
            $integration->active = false;
            $integration->save();
            $this->notificationService->notifyAdminIntegrationDisabled($integration, $e);
        }

        return $resultDto;
    }

    /**
     * @param Integration $integration
     * @param array $callStat
     * @return Call|null
     */
    private function processIntegrationPbxCallStat(Integration $integration, array $callStat): ?Call
    {
        if (!!$callStat) {
            try {
                $callTime = isset($callStat['callstart']) ? strtotime($callStat['callstart']) : null;
                $pbxCallId = $callStat['pbx_call_id'] ?? null;
                $direction = $this->zadarmaService->getCallDirectionByPbxCallId($pbxCallId);
                $callFrom = $this->zadarmaService->parseClid($integration, $callStat);
                $callTo = $callStat['destination'] ?? null;
                $status = $callStat['disposition'] == "answered" ? 'answered' : 'missed';
                $duration = (int)($callStat['seconds'] ?? 0);
                $operator = null;
                $recordingDownloadStatus = $this->downloadAndSaveZadarmaRecord($integration, $callStat);

                if (
                    Call::where('integration_id', $integration->id)
                    ->where('external_call_id', $pbxCallId)
                    ->exists()
                ) {
                    return null;
                }

                $call = Call::updateOrCreate([
                    'integration_id' => $integration->id,
                    'external_call_id' => $pbxCallId,
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
                $integration->active = false;
                $integration->save();
            }
        }

        return null;
    }

    /**
     * Скачать и сохрнить запись разговора
     * @param Integration $integration
     * @param array $callStat
     * @return IntegrationProcessDownloadStatusDTO
     */
    private function downloadAndSaveZadarmaRecord(Integration $integration, array $callStat): IntegrationProcessDownloadStatusDTO
    {
        if (!!$callStat && isset($callStat['is_recorded']) && !!$callStat['is_recorded']) {
            $api = $this->zadarmaService->getApiClient($integration->api_key, $integration->secret);
            $pbxCallId = $callStat['pbx_call_id'];
            $pbxRecord = null;

            try {
                if (!!$pbxCallId) {
                    $pbxRecord = $api->getPbxRecord(null, $callStat['pbx_call_id']);
                }

                if (!!$pbxRecord) {
                    $link = null;
                    if (Str::of($pbxRecord->link)->isNotEmpty()) {
                        $link = $pbxRecord->link;
                    } elseif (!!$pbxRecord->links && count($pbxRecord->links) > 0) {
                        $link = $pbxRecord->links[0];
                    }

                    $fileName = "recordings/zadarma_$pbxCallId.mp3";
                    $recording = $this->downloadAndSaveRecord($pbxCallId, $fileName, $link);
                    if (!!$recording) {
                        return new IntegrationProcessDownloadStatusDTO($recording, 'uploaded');
                    }
                }
            } catch (Throwable $e) {
                Log::error("Ошибка при скачивании записи Zadarma: " . $e->getMessage());
            }
        }

        return new IntegrationProcessDownloadStatusDTO(null, null);
    }
}
