<?php

namespace App\Console\Commands\Base;

use App\Models\Integration;
use App\Services\IntegrationProcess\IntegrationProcessService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Базовый класс команды для обработки интеграций
 */
abstract class FetchCallsCommand extends TelephonyCommand
{
    private IntegrationProcessService $integrationProcessService;

    public function __construct(IntegrationProcessService $integrationProcessService)
    {
        parent::__construct();
        $this->integrationProcessService = $integrationProcessService;
    }

    /**
     * @return Builder|Integration
     */
    abstract protected function getIntegrations(): Integration|Builder;

    public function handle()
    {
        $this->info("$this->signature start");
        $this->getIntegrations()->each(function ($integration) {
            $this->info("$this->signature for integration $integration->id started");
            $result = $this->integrationProcessService->processIntegration($integration);
            if (!!$result) {
                $count = $result->callsCount ?? 0;
                $from = $result->from ?? "null";
                $to = $result->to ?? "null";
                $this->info("$this->signature for integration $integration->id finished | callsCount=$count | from=$from | to=$to");
            }
        });
    }
}
