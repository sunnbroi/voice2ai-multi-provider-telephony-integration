<?php

namespace App\Console\Commands;

use App\Jobs\SendTelegramMessageJob;
use App\Models\Integration;
use Illuminate\Console\Command;

class DailyCallReport extends Command
{
    protected $signature = 'report:daily-calls';

    protected $description = 'Send daily call report to Telegram';

    public function handle()
    {
        Integration::active()->each(function ($integration) {
            $calls = $integration->calls()->whereDate('created_at', today())->get();

            $incoming = $calls->where('direction', 'in');
            $outgoing = $calls->where('direction', 'out');

            $incomingTotal = $incoming->count();
            $incomingMissed = $incoming->where('status', 'missed')->count();

            $outgoingTotal = $outgoing->count();
            $outgoingMissed = $outgoing->where('status', 'missed')->count();

            $conflictCount = $calls->where('is_conflict', true)->count();

            $msg = "📊 Отчет за день:\n\n";
            $msg .= "Входящие звонки: {$incomingTotal}\n";
            $msg .= "Из них не отвечено: {$incomingMissed}\n";
            $msg .= "Исходящие звонки: {$outgoingTotal}\n";
            $msg .= "Из них не отвечено: {$outgoingMissed}\n";
            $msg .= "Конфликтные: {$conflictCount}\n";
            $msg .= 'Жалобы: -';

            if ($integration->telegram_chat_id) {
                SendTelegramMessageJob::dispatch($integration->telegram_chat_id, $msg);
                SendTelegramMessageJob::dispatch(config('services.telegram.admin_chat_id'), $msg);
            }
        });

        $this->info('Reports sent.');
    }
}
