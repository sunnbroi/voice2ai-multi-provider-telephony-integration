<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Actions\SendTelegramMessage;

class SendTelegramMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected string $chatId;
    protected string $message;
    protected ?array $replyMarkup;

    public function __construct(string $chatId, string $message, ?array $replyMarkup = null)
    {
        $this->chatId = $chatId;
        $this->message = $message;
        $this->replyMarkup = $replyMarkup;
    }

    public function handle(SendTelegramMessage $sender): void
    {
        $sender->__invoke($this->chatId, $this->message, $this->replyMarkup);
    }
}
