<?php

namespace App\Services\IntegrationProcess;

use App\DTO\IntegrationProcess\IntegrationProcessResultDTO;
use App\Jobs\SendTelegramMessageJob;
use App\Models\Call;
use App\Models\Integration;
use App\Services\BaseService;
use App\Services\Notification\NotificationService;
use App\Services\OpenAi\OpenAIService;
use App\Services\Telegram\TelegramService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * IntegrationProcessService
 */
abstract class IntegrationProcessService extends BaseService
{
    protected OpenAIService $openAIService;
    protected TelegramService $telegramService;
    protected NotificationService $notificationService;

    public function __construct(
        OpenAIService $openAIService,
        TelegramService $telegramService,
        NotificationService $notificationService
    ) {
        parent::__construct();
        $this->openAIService = $openAIService;
        $this->telegramService = $telegramService;
        $this->notificationService = $notificationService;
    }

    /**
     * Начать обработку звонков для интеграции
     * @param Integration $integration
     * @return IntegrationProcessResultDTO|null
     */
    abstract public function processIntegration(Integration $integration): ?IntegrationProcessResultDTO;

    /**
     * @param string $externalCallId id записи во внейшей системе
     * @param string $fileName имя записи во внейшей системе
     * @param string $fileUrl url записи во внейшей системе
     * @return string|null url записи в нашей системе
     * @throws Throwable
     */
    protected function downloadAndSaveRecord(string $externalCallId, string $fileName, string $fileUrl): ?string
    {
        try {
            Log::info("Звонок {$externalCallId} fileName=$fileName | fileUrl=$fileUrl");
            $fileContent = @file_get_contents($fileUrl);
            if ($fileContent !== false) {
                Storage::disk('public')->put($fileName, $fileContent);
                return Storage::url($fileName);
            } else {
                Log::error("Ошибка при скачивании файла $externalCallId");
            }
        } catch (Throwable $e) {
            Log::error("Ошибка при скачивании файла $externalCallId: " . $e->getMessage());
            throw $e;
        }

        return null;
    }

    /**
     * Обработка звонка
     * @param Call $call
     * @return void
     */
    public function proccessCall(Call $call)
    {
        if ($call->status === 'answered') {
            $this->processCallSummary($call); // с резюме
        } else {
            $this->processMissedCall($call);
        }
    }

    /**
     * Логика обработки звонка (транскрибация, чат, телега)
     * @param Call $call
     * @return void
     */
    public function processCallSummary(Call $call)
    {
        $this->openAIService->processCallSummary($call);
    }

    /**
     * Обработка пропущенного звонка
     * @param Call $call
     * @return void
     */
    public function processMissedCall(Call $call)
    {
        /**
         * @var $integration Integration
         */
        $integration = $call->integration;

        if ($integration->active_tg_notify_client) {
            // Отправим короткое уведомление по пропущенному звонку
            $isIncoming = $call->direction === 'in';
            $arrow = $isIncoming ? '➟📱' : '📱➟';
            $directionLabel = $isIncoming ? 'Входящий' : 'Исходящий';
            $msg = "{$arrow}{$directionLabel} звонок\n";
            $msg .= "От: {$call->from_phone}\nКому: {$call->to_phone}\n";
            $msg .= "Статус: ❌Не отвечен";

            if ($call->operator_name) {
                $msg .= "\nОператор: {$call->operator_name}";
            }

            SendTelegramMessageJob::dispatch($integration->telegram_chat_id, $msg);
            Log::info("Уведомление по пропущенному звонку $call->id отправлено");
        } else {
            Log::info("Уведомление по пропущенному звонку $call->id не отправленно, т.к. отключен отправка клиенту");
        }
    }


    /**
     * ПОбработать звонки, в которых запись в статусе uploading
     * @param int $limit
     * @return void
     */
    public function processPendingRecordings(int $limit = 100)
    {
    }
}
