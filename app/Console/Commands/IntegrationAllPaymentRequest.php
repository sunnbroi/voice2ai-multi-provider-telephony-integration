<?php

namespace App\Console\Commands;

use App\Jobs\SendTelegramMessageJob;
use App\Models\Integration;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class IntegrationAllPaymentRequest extends Command
{
    protected $signature = 'payment-request:integration-all';
    protected $description = 'Payment Request for All Active integrations';

    public function handle()
    {
        Integration::active()->each(function ($integration) {
            try {
                Artisan::call('collecting-money:month', [
                    'integrationId' => $integration->id,
                ]);
            } catch (Exception $e) {
                $this->error("Failed Payment Request for integration {$integration->id}: " . $e->getMessage());
            }
        });
    }
}
