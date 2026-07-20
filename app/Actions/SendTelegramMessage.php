<?php

namespace App\Actions;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendTelegramMessage
{
    public function __invoke(string $chatId, string $message, ?array $replyMarkup = null): bool
    {
        $botToken = config('services.telegram.bot_token');

        $data = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
        ];

        if ($replyMarkup !== null) {
            $data['reply_markup'] = json_encode($replyMarkup);
        }

        $response = Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", $data);
        //Log::info($response->body());

        return $response->successful();
    }
}
