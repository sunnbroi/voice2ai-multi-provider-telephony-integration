<?php

namespace App\Services\Zadarma;

use App\Models\Integration;
use App\Services\BaseService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Zadarma_API\Api;
use Zadarma_API\ApiException;
use Zadarma_API\Response\PbxInfo;
use Zadarma_API\Response\PbxStatistics;

/**
 * Сервис интеграции с Zaaarma
 */
class ZadarmaService extends BaseService
{
    public const USE_SANDBOX = false;

    /**
     * Получить API клиент
     * @param string $apiKey
     * @param string $apiSecret
     * @return Api
     */
    public function getApiClient(string $apiKey, string $apiSecret): Api
    {
        return new Api($apiKey, $apiSecret, ZadarmaService::USE_SANDBOX);
    }

    /**
     * Получить API клиента
     * @param Integration $integration
     * @return Api
     */
    public function getApiClientByIntegration(Integration $integration): Api
    {
        return $this->getApiClient($integration->api_key, $integration->secret);
    }

    /**
     * Получить статистику по звнокам АТС
     *
     * @param Integration $integration
     * @param string|null $from
     * @param string|null $to
     * @param string|null $callType IN_CALLS for incoming calls, OUT_CALLS for outgoing, null for both
     * @param int|null $skip
     * @param int|null $limit
     * @return PbxStatistics
     * @throws ApiException
     */
    public function getPbxStatistics(
        Integration $integration,
        ?string $from,
        ?string $to,
        ?string $callType = null,
        ?int $skip = 0,
        ?int $limit = 500
    ): PbxStatistics {
        $api = $this->getApiClientByIntegration($integration);
        return $api->getPbxStatistics($from, $to, true, $callType, $skip, $limit);
    }

    /**
     * @param Integration $integration
     * @param string $pbxId
     * @return PbxInfo
     */
    public function getPbxInfo(Integration $integration, string $pbxId): PbxInfo
    {
        return Cache::remember("ZadarmaService.getPbxInfo($integration->id,$pbxId)", now()->addMinute(), function () use ($integration, $pbxId) {
            $api = $this->getApiClientByIntegration($integration);
            return $api->getPbxInfo($pbxId);
        });
    }

    /**
     * Проверить что in звонок по pbx_call_id
     * @param $pbxCallId
     * @return bool
     */
    public function isInPbxCallId($pbxCallId): bool
    {
        return Str::of($pbxCallId)->isNotEmpty() && str_starts_with($pbxCallId, 'in');
    }

    /**
     * Проверить что out звонок по pbx_call_id
     * @param $pbxCallId
     * @return bool
     */
    public function isOutPbxCallId($pbxCallId): bool
    {
        return Str::of($pbxCallId)->isNotEmpty() && str_starts_with($pbxCallId, 'out');
    }

    /**
     * Проверить направление звонка по pbx_call_id
     * @param $pbxCallId
     * @return string|null
     */
    public function getCallDirectionByPbxCallId($pbxCallId): ?string
    {
        if ($this->isInPbxCallId($pbxCallId)) {
            return 'in';
        } elseif ($this->isOutPbxCallId($pbxCallId)) {
            return 'out';
        } else {
            return 'in';
        }
    }

    private function __parseClid(Integration $integration, array $callStat): ?string
    {
        $pbxCallId = $callStat['pbx_call_id'] ?? null;
        $clid = $callStat['clid'] ?? null;

        if (Str::of($pbxCallId)->isNotEmpty() && Str::of($clid)->isNotEmpty()) {
            if ($this->isInPbxCallId($pbxCallId)) {
                if (preg_match('/<([^>]+)>/', $clid, $matches)) {
                    return $matches[1];
                }
            }

            if ($this->isOutPbxCallId($pbxCallId)) {
                if (str_starts_with($clid, 'Extension')) {
                    $pbxSipId = $callStat['sip'] ?? null;
                    $pbxInfo = $this->getPbxInfo($integration, $pbxSipId);
                    if (!!$pbxInfo) {
                        return $pbxInfo->caller_id;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param Integration $integration
     * @param array $callStat
     * @return string|null
     */
    public function parseClid(Integration $integration, array $callStat): ?string
    {
        return $this->__parseClid($integration, $callStat);
    }
}
