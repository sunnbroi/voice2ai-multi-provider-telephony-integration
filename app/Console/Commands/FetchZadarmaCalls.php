<?php

namespace App\Console\Commands;

use App\Console\Commands\Base\FetchCallsCommand;
use App\Models\Integration;
use App\Services\IntegrationProcess\Zadarma\ZadarmaIntegrationProcessService;
use Illuminate\Database\Eloquent\Builder;

class FetchZadarmaCalls extends FetchCallsCommand
{
    protected $signature = 'zadarma:fetch-calls';
    protected $description = 'Fetch new calls from Zadarma PBX API and save records';

    public function __construct(ZadarmaIntegrationProcessService $zadarmaIntegrationProcessSerice)
    {
        parent::__construct($zadarmaIntegrationProcessSerice);
    }

    protected function getIntegrations(): Integration|Builder
    {
        return Integration::active()->zadarma();
    }
}
