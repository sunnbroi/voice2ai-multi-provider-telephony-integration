<?php

namespace App\Console\Commands;

use App\Console\Commands\Base\FetchCallsCommand;
use App\Models\Integration;
use App\Services\IntegrationProcess\Unitalk\UnitalkIntegrationProcessService;
use Illuminate\Database\Eloquent\Builder;

class FetchUnitalkCalls extends FetchCallsCommand
{
    protected $signature = 'unitalk:fetch-calls';
    protected $description = 'Fetch new calls (incoming & outgoing) from Unitalk API and save recordings';

    public function __construct(UnitalkIntegrationProcessService $unitalkIntegrationProcessService)
    {
        parent::__construct($unitalkIntegrationProcessService);
    }

    protected function getIntegrations(): Integration|Builder
    {
        return Integration::active()->unitalk();
    }
}
