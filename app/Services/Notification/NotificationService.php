<?php

namespace App\Services\Notification;

use App\Mail\NotificationMail;
use App\Models\Integration;
use App\Services\BaseService;
use Exception;
use Illuminate\Support\Facades\Mail;

use function PHPUnit\Framework\isNull;

/**
 * Сервис уведомлений
 */
class NotificationService extends BaseService
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @return string[]
     */
    private function getAdminEmails(): array
    {
        return explode(';', config('services.notification.admin_emails'));
    }

    /**
     * Выслать notification письмо администратору на почту
     * @param string $text
     * @param string|null $subject
     * @return void
     */
    public function sendAdminEmailNotification(string $text, ?string $subject = null)
    {
        if (isNull($subject)) {
            Mail::to($this->getAdminEmails())->send(new NotificationMail($text));
        } else {
            Mail::to($this->getAdminEmails())->send(new NotificationMail($text, $subject));
        }
    }

    /**
     * Выслать уведомление админинстратору, что интеграция отключена
     * @param Integration $integration интеграция
     * @param Exception|null $exception ошибка в интеграции
     * @return void
     */
    public function notifyAdminIntegrationDisabled(Integration $integration, ?Exception $exception = null)
    {
        $textArray = [
            "Интеграция отключена | id = $integration->id | title = $integration->title"
        ];

        if ($exception != null) {
            $textArray[] = "code: {$exception->getCode()}, maessage: {$exception->getMessage()}";
            $textArray[] = $exception->getTraceAsString();
        }

        $this->sendAdminEmailNotification(
            implode(PHP_EOL, $textArray),
            "Интеграция $integration->id отключена"
        );
    }
}
