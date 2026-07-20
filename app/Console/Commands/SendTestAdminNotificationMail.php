<?php

namespace App\Console\Commands;

use App\Mail\NotificationMail;
use App\Services\Notification\NotificationService;
use Illuminate\Console\Command;

class SendTestAdminNotificationMail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-admin-notification-mail';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->notificationService->sendAdminEmailNotification('Это тестовое письмо уведомления');
        $this->info("Тестовое письмо отправлено администратором");
    }
}
