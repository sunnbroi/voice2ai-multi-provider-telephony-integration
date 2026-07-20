<?php

namespace App\Console\Commands;

use App\Console\Commands\Base\FetchCallsCommand;
use App\Models\Integration;
use App\Services\IntegrationProcess\Phonet\PhonetIntegrationProcessService;
use Illuminate\Database\Eloquent\Builder;

class FetchPhonetCalls extends FetchCallsCommand
{
    protected $signature = 'phonet:fetch-calls';
    protected $description = 'Fetch new calls (incoming & outgoing) from Unitalk API and save recordings';

    public function __construct(PhonetIntegrationProcessService $phonetIntegrationProcessService)
    {
        parent::__construct($phonetIntegrationProcessService);
    }

    protected function getIntegrations(): Integration|Builder
    {
        return Integration::active()->phonet();
    }
}
