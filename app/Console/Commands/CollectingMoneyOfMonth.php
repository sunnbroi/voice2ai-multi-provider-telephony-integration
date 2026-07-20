<?php

namespace App\Console\Commands;

use App\Jobs\SendTelegramMessageJob;
use App\Models\Integration;
use App\Services\Telegram\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CollectingMoneyOfMonth extends Command
{
    protected $signature = 'collecting-money:month {integrationId}';
    protected $description = 'Deactivate integration and send pay menu to telegram';

    public function handle(TelegramService $telegramService)
    {
        $integrationId = $this->argument('integrationId');
        $integration = Integration::find($integrationId);
        $integration->active = false;
        $integration->is_paid = false;

        //расчет долга начало
        //пересчитываем, если сейчас нет долга
        if ($integration->debt_price == 0) {
            $tariffData = $integration->getTariffData();
            $currency = $tariffData->currency;
            $priceOfMinute = $tariffData->priceOfMinute;

            $previousMonthStart = Carbon::now()->subMonth()->startOfMonth();
            $previousMonthEnd = Carbon::now()->subMonth()->endOfMonth();

            $monthCallsDurationSum = $integration->calls()
                ->whereBetween('created_at', [
                    $previousMonthStart,
                    $previousMonthEnd,
                ])
                ->sum('duration');

            $totalMinutes = floor($monthCallsDurationSum / 60);
            $totalPrice = $totalMinutes * $priceOfMinute;


            $integration->debt_minutes = $totalMinutes;
            $integration->debt_price = $totalPrice;
            $integration->debt_currency = $currency;
            $integration->debt_tariff_id = $integration->tariff->id;
        }
        //расчет долга конец

        $integration->save();
        $telegramService->handlePayMenu($integrationId);
    }
}
