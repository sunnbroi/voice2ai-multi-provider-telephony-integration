<?php

namespace App\Jobs;

use App\Jobs\SendTelegramMessageJob;
use OpenAI\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class SendToChatGPTJob
{
    protected $call;

    public function __construct($call)
    {
        $this->call = $call;
    }

    public function handle()
    {
        $call = $this->call;

        Log::info("Обновлен чат гпт ");

        if (!$call->recording_url || !$call->integration || !$call->integration->telegram_chat_id) {
            Log::warning("Call {$call->id} не имеет записи или Telegram ID");
            return;
        }

        $openAiKey = env('OPENAI_API_KEY');
        if (!$openAiKey) {
            Log::error('OPENAI_API_KEY не установлен');
            return;
        }

        $client = (new Factory())->withApiKey($openAiKey)->make();

        $localPath = storage_path('app/public/recordings/' . basename($call->recording_url));

        try {
            $transcription = $client->audio()->transcribe([
                'model' => 'whisper-1',
                'file' => fopen($localPath, 'r'),
                'response_format' => 'text',
            ]);

            $text = $transcription->text ?? (string) $transcription;

            $response = $client->chat()->create([
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => 'Ты помощник, делающий краткое резюме текста.'],
                    ['role' => 'user', 'content' => "Убирай очевидные вещи... {$text}"],
                ],
            ]);

            $summary = trim($response->choices[0]->message->content);

            $call->summary = $summary;
            $call->save();

            $integration = $call->integration;
            $isIncoming = $call->direction === 'in';
            $arrow = $isIncoming ? '➟📱' : '📱➟';
            $directionLabel = $isIncoming ? 'Входящий' : 'Исходящий';
            $statusLabel = $call->status === 'answered' ? '✅Отвечен' : '❌Не отвечен';

            $msg = "{$arrow}{$directionLabel} звонок\nОт: {$call->from_phone}\nКому: {$call->to_phone}\nСтатус: {$statusLabel}";

            if ($call->operator_name) {
                $msg .= "\nОператор: {$call->operator_name}";
            }

            if ($call->duration > 0) {
                $url = "https://" . env('RECORD_DOMAIN') . "/listen/{$call->id}/" . basename($call->recording_url);
                $msg .= "\nАудиозапись: <a href=\"{$url}\">" . gmdate('i:s', $call->duration) . "</a>";
            }

            if ($summary) {
                $msg .= "\n {$summary}";
            }

            SendTelegramMessageJob::dispatch($integration->telegram_chat_id, $msg);
            Log::info("Отправлено уведомление в основной Telegram чат для звонка {$call->id}");

            SendTelegramMessageJob::dispatch(config('services.telegram.admin_chat_id'), $msg);
            Log::info("Доп. уведомление отправлено на user_id " . config('services.telegram.admin_chat_id') . " для звонка {$call->id}");
        } catch (\Throwable $e) {
            Log::error("Ошибка в SendToChatGPTJob для call_id {$call->id}: {$e->getMessage()}");
        }
    }
}
