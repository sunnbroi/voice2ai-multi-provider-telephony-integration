<?php

namespace App\Console\Commands;

use App\Console\Commands\Base\FetchCallsCommand;
use App\Models\Integration;
use App\Services\IntegrationProcess\Binotel\BinotelIntegrationProcessService;
use Illuminate\Database\Eloquent\Builder;

class FetchBinotelCalls extends FetchCallsCommand
{
    protected $signature = 'binotel:fetch-calls';
    protected $description = 'Fetch new calls (incoming & outgoing) from Binotel API and save recordings';

    public function __construct(BinotelIntegrationProcessService $binotelIntegrationProcessService)
    {
        parent::__construct($binotelIntegrationProcessService);
    }

    protected function getIntegrations(): Integration|Builder
    {
        return Integration::active()->binotel();
    }
}
