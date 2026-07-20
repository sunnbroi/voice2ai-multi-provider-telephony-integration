<?php

namespace App\Console\Commands;

use App\Models\Call;
use App\Models\Integration;
use App\Services\IntegrationProcess\Binotel\BinotelIntegrationProcessService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class FetchBinotelPendingRecordings extends Command
{
    protected $signature = 'binotel:fetch-recordings';
    protected $description = 'Check and download pending call recordings with status "uploading"';

    private BinotelIntegrationProcessService $binotelIntegrationProcessService;

    public function __construct(BinotelIntegrationProcessService $binotelIntegrationProcessService)
    {
        parent::__construct();
        $this->binotelIntegrationProcessService = $binotelIntegrationProcessService;
    }

    public function handle()
    {
        $this->binotelIntegrationProcessService->processPendingRecordings();
    }
}
