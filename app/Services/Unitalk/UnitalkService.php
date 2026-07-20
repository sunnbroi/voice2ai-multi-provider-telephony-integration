<?php

namespace App\Services\Unitalk;

use App\Models\Integration;
use App\Services\BaseService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Сервис для Unitalk
 */
class UnitalkService extends BaseService
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Получить историю звонков
     * @param Integration $integration
     * @param string $dateFrom
     * @param string $dateTo
     * @return array
     * @throws ConnectionException
     */
    public function getCallsHistory(Integration $integration, string $dateFrom, string $dateTo): array
    {
        $payload = [
            "dateFrom" => $dateFrom,
            "dateTo" => $dateTo,
            "limit" => 500,
            "offset" => 0
        ];

        $calls = Http::withToken($integration->api_key)->post('https://api.unitalk.cloud/api/history/get', $payload);
        return $calls->json();
    }
}
