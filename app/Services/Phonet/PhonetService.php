<?php

declare(strict_types=1);

namespace App\Services\Phonet;

use App\Models\Integration;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use RuntimeException;
use Illuminate\Http\Client\PendingRequest;

class PhonetService
{
    private HttpFactory $http;

    /** @var array<string,string> */
    protected array $cookies = [];

    public function __construct(HttpFactory $http)
    {
        $this->http = $http;
    }

    /**
     * Получить историю звонков Phonet (company.api) с пагинацией.
     *
     * @param Integration $integration  Интеграция (должны быть host и api_key)
     * @param int         $fromMs       Начало окна в миллисекундах (UTC)
     * @param int         $toMs         Конец окна в миллисекундах (UTC)
     * @param int[]       $directions   2 (out), 4 (in). По умолчанию [2,4]
     * @param int         $limit        Размер страницы, максимум 50
     *
     * @return array{count:int,calls:array<int,array<string,mixed>>}
     */
    public function getCallsHistory(
        Integration $integration,
        int $fromMs,
        int $toMs,
        array $directions = [2, 4],
        int $limit = 50
    ): array {
        [$domain, $apiKey] = $this->resolveCredentials($integration);
        if ($domain === '' || $apiKey === '') {
            throw new RuntimeException('Phonet getCallsHistory: missing domain/api_key');
        }

        if (empty($this->cookies)) {
            $this->authorize($integration);
        }

        $baseUrl = "https://{$domain}/rest/calls/company.api";
        $limit   = max(1, min(50, $limit));
        $offset  = 0;

        $dirs = array_values(array_unique(array_map('intval', $directions)));
        $directionsMask = !empty($dirs) ? array_sum($dirs) : null;

        $all = [];
        while (true) {
            $query = [
            'timeFrom' => $fromMs,
            'timeTo'   => $toMs,
            'limit'    => $limit,
            'offset'   => $offset,
            ];
            if ($directionsMask !== null) {
                $query['directions'] = $directionsMask; // 2|4 = 6
            }

            $request = $this->newHttp()
            ->withHeaders(['Accept' => 'application/json, */*'])
            ->withCookies($this->cookies, $domain);

            /** @var \Illuminate\Http\Client\Response $resp */
            $resp = $request->get($baseUrl, $query);

            // если сессия умерла (401/403) или редирект без куки — перевыпустить
            if (in_array($resp->status(), [401, 403, 302], true)) {
                $this->authorize($integration);
                $resp = $request->withCookies($this->cookies, $domain)->get($baseUrl, $query);
            }

            if ($resp->failed()) {
                throw new RuntimeException("Phonet getCallsHistory failed: HTTP {$resp->status()}");
            }

            $data  = $resp->json();
            $items = $this->extractItems($data);

            foreach ($items as $row) {
                if (is_array($row)) {
                    $all[] = $row;
                }
            }

            if (count($items) < $limit) {
                break;
            }
            $offset += $limit;
        }

        return ['count' => count($all), 'calls' => $all];
    }

    /**
     * Авторизация в Phonet: POST /rest/security/authorize c apiKey.
     * Сохраняет cookie (например, JSESSIONID) в $this->cookies.
     */
    private function newHttp(): PendingRequest
    {
        $verify = (bool) (config('services.phonet.verify_ssl', true));
        return $this->http
        ->timeout(15)
        ->connectTimeout(6)
        ->withOptions(['verify' => $verify]);
    }
    private function extractItems(mixed $data): array
    {
        if (!is_array($data)) {
            return [];
        }

        // Самые частые варианты контейнеров
        foreach (['items', 'calls', 'content', 'data', 'list'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $data[$key];
            }
        }

        // Если API вернул массив верхнего уровня — тоже считаем списком
        $isList = !empty($data) && array_is_list($data);
        return $isList ? $data : [];
    }
/**
 * Авторизация в Phonet через POST /rest/security/authorize (x-www-form-urlencoded).
 * Только form-параметры. Никакого JSON.
 */
/**
 * Авторизация в Phonet: перебираем безопасные варианты до получения JSESSIONID.
 * Порядок:
 * 1) POST x-www-form-urlencoded на /rest/security/authorize с apiKey+companyId
 * 2) GET на /rest/security/authorize с query (apiKey+companyId)
 * 3) POST form на /rest/security/authorize.api
 * 4) GET на /rest/security/authorize.api
 * Во всех шагах пробуем альтернативные имена поля компании: companyId, companyID, company.
 * Считаем 200/204/302 успехом при наличии Set-Cookie: JSESSIONID=...
 */
    public function authorize(Integration $integration): bool
    {
        [$domain, $apiKey] = $this->resolveCredentials($integration);
        if ($domain === '' || $apiKey === '') {
            throw new RuntimeException('Phonet authorize: missing domain/api_key');
        }

        $companyId = $integration->company_id ? trim((string) $integration->company_id) : null;
        $apiKey    = trim($apiKey);

        $endpoints = [
        "https://{$domain}/rest/security/authorize",
        "https://{$domain}/rest/security/authorize.api",
        ];

        // Варианты имён параметра компании, встречающиеся на разных стендах
        $companyKeyVariants = array_filter([
        $companyId !== null && $companyId !== '' ? ['companyId' => $companyId] : null,
        $companyId !== null && $companyId !== '' ? ['companyID' => $companyId] : null,
        $companyId !== null && $companyId !== '' ? ['company'   => $companyId] : null,
        // Дополнительно пробуем без companyId вовсе — на части стендов apiKey сам биндит компанию
        [],
        ]);

        // Обёртки-отправители
        $sendPostForm = function (string $url, array $data): Response {
            // ручное формирование тела, чтобы исключить любые расхождения asForm()
            $ct   = 'application/x-www-form-urlencoded; charset=UTF-8';
            $body = http_build_query($data, '', '&', PHP_QUERY_RFC1738);

            return $this->newHttp()
            ->withHeaders([
                'Accept'       => 'application/json, */*',
                'Content-Type' => $ct,
            ])
            ->retry(1, 200)
            ->withBody($body, $ct)
            ->post($url);
        };

        $sendGet = function (string $url, array $data): Response {
            return $this->newHttp()
            ->withHeaders(['Accept' => 'application/json, */*'])
            ->retry(1, 200)
            ->get($url, $data);
        };

        $lastResp = null;

        foreach ($endpoints as $idxEp => $url) {
            foreach ($companyKeyVariants as $companyChunk) {
                $payload = array_merge(['apiKey' => $apiKey], $companyChunk);

                // 1) POST form
                $resp = $sendPostForm($url, $payload);
                $lastResp = $resp;
                if ($this->tryExtractCookie($resp, $url, 'POST-FORM', $payload)) {
                    return true;
                }

                // Если 415 — часто помогает GET-авторизация с querystring (балансировщики/фильтры)
                if ($resp->status() === 415 || $resp->status() === 405 || $resp->status() === 400 || $resp->status() === 404 || $resp->status() === 401) {
                    // 2) GET
                    $resp = $sendGet($url, $payload);
                    $lastResp = $resp;
                    if ($this->tryExtractCookie($resp, $url, 'GET', $payload)) {
                        return true;
                    }
                }
            }
        }

        \Log::error('Phonet authorize failed (exhausted)', [
        'status' => $lastResp?->status(),
        'body'   => mb_strimwidth((string)($lastResp?->body() ?? ''), 0, 800, '…'),
        ]);
        throw new RuntimeException("Phonet authorize failed: HTTP " . ($lastResp?->status() ?? 0));
    }

/**
 * Пытается вытащить JSESSIONID из ответа и залить в $this->cookies.
 * Возвращает true при успехе.
 */
    private function tryExtractCookie(Response $resp, string $url, string $mode, array $payload): bool
    {
        // Считаем успехом 200/201/204/302 при наличии Set-Cookie
        $okStatuses = [200, 201, 204, 302];
        if (!in_array($resp->status(), $okStatuses, true)) {
            \Log::warning('Phonet authorize attempt failed', [
            'endpoint'        => $url,
            'mode'            => $mode,
            'status'          => $resp->status(),
            'with_company_key' => array_key_exists('companyId', $payload) || array_key_exists('companyID', $payload) || array_key_exists('company', $payload),
            'body'            => mb_strimwidth((string)$resp->body(), 0, 500, '…'),
            ]);
            return false;
        }

        $cookies = $this->parseSetCookieHeader($resp->header('Set-Cookie'));

        if (empty($cookies)) {
            // Иногда сервер кладёт ID сессии в JSON
            $json = $resp->json();
            if (is_array($json)) {
                foreach (['JSESSIONID', 'jsessionid', 'session'] as $k) {
                    if (!empty($json[$k]) && is_string($json[$k])) {
                        $cookies[$k] = $json[$k];
                    }
                }
            }
        }

        if (!isset($cookies['JSESSIONID'])) {
            foreach ($cookies as $k => $v) {
                if (strcasecmp($k, 'JSESSIONID') === 0) {
                    $cookies['JSESSIONID'] = $v;
                    break;
                }
            }
        }

        if (empty($cookies['JSESSIONID'] ?? null)) {
            \Log::warning('Phonet authorize: success HTTP but no JSESSIONID cookie', [
            'endpoint' => $url,
            'mode'     => $mode,
            'status'   => $resp->status(),
            'set_cookie_present' => $resp->header('Set-Cookie') !== null,
            ]);
            return false;
        }

        $this->cookies = $cookies;
        return true;
    }



    /**
     * Парсим заголовок Set-Cookie (или массив заголовков) в карту cookie.
     *
     * @param string|array<string>|null $setCookieHeader
     * @return array<string,string>
     */
    private function parseSetCookieHeader(string|array|null $setCookieHeader): array
    {
        $cookies = [];

        if (is_string($setCookieHeader)) {
            $setCookieHeader = [$setCookieHeader];
        }

        if (!is_array($setCookieHeader)) {
            return $cookies;
        }

        foreach ($setCookieHeader as $line) {
            // Пример: "JSESSIONID=abc123; Path=/; HttpOnly"
            if (preg_match('/^([^=;,\s]+)=([^;]+)/', $line, $m)) {
                $name  = trim($m[1]);
                $value = trim($m[2]);
                if ($name !== '' && $value !== '') {
                    $cookies[$name] = $value;
                }
            }
        }

        return $cookies;
    }

    /**
     * Достаёт host и apiKey из Integration:
     * 1) $integration->domain (если есть)
     * 2) иначе $integration->secret (у тебя тут лежит host Phonet)
     * (опционально) можно добавить чтение из JSON settings
     *
     * @return array{0:string,1:string} [$domain, $apiKey]
     */
    private function resolveCredentials(Integration $integration): array
    {
        $domain = trim((string)($integration->domain ?? ''));
        $apiKey = trim((string)($integration->api_key ?? ''));

        if ($domain === '' && !empty($integration->secret)) {
            $domain = trim((string)$integration->secret);
        }

        // Если используете JSON-настройки, раскомментируй при необходимости:
        // if (($domain === '' || $apiKey === '') && is_array($integration->settings ?? null)) {
        //     $s = $integration->settings;
        //     $domain = $domain !== '' ? $domain : (string)($s['phonet']['domain'] ?? $s['domain'] ?? '');
        //     $apiKey = $apiKey !== '' ? $apiKey : (string)($s['phonet']['api_key'] ?? $s['api_key'] ?? '');
        // }

        if ($domain !== '') {
            $domain = preg_replace('#^https?://#i', '', $domain);
            $domain = rtrim($domain, '/');
        }

        return [$domain, $apiKey];
    }
}
