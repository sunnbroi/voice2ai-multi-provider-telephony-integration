<?php

namespace App\Console\Commands;

use App\Models\Call;
use App\Models\Integration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FetchZadarmaRecordings extends Command
{
    protected $signature = 'zadarma:fetch-recordings';
    protected $description = 'Догружает записи звонков Zadarma для звонков со статусом recording_status=uploading';

    public function handle(): int
    {
        $calls = Call::where('recording_status', 'uploading')
            ->whereNotNull('integration_id')
            ->whereHas('integration.provider', function ($q) {
                $q->where('name', 'Zadarma');
            })
            ->with('integration')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        if ($calls->isEmpty()) {
            $this->info('Нет звонков со статусом uploading для Zadarma');
            return 0;
        }

        foreach ($calls as $call) {
            $integration = $call->integration;
            if (!$integration) {
                continue;
            }

            // Попробуем получить свежую статистику по конкретному звонку, чтобы узнать ссылку на запись
            // У Zadarma нет прямого эндпоинта по id записи в публичной доке, поэтому повторно опрашиваем статистику за небольшой интервал вокруг звонка
            $callTs = method_exists($call->call_time, 'getTimestamp')
                ? $call->call_time->getTimestamp()
                : strtotime((string) $call->call_time);
            $start = gmdate('Y-m-d H:i:s', max(0, $callTs - 3600));
            $end = gmdate('Y-m-d H:i:s', $callTs + 3600);

            $params = [
                'start' => $start,
                'end' => $end,
                'limit' => 500,
            ];

            $resp = $this->zadarmaGet('https://api.zadarma.com/v1/statistics/pbx/', $params, $integration->api_key, $integration->secret);

            if (!$resp || !$resp->ok()) {
                Log::warning("Не удалось обновить запись для звонка {$call->id}: запрос статистики неуспешен");
                continue;
            }

            $stats = $resp->json('stats') ?? [];
            $found = collect($stats)->first(function ($item) use ($call) {
                $ext = $item['pbx_call_id'] ?? ($item['call_id'] ?? null);
                return $ext && $ext === $call->external_call_id;
            });

            if (!$found) {
                continue;
            }

            $recordLink = $found['record_link'] ?? null;
            if (!$recordLink) {
                continue;
            }

            try {
                $fileContent = @file_get_contents($recordLink);
                if ($fileContent === false) {
                    continue;
                }
                $fileName = "recordings/" . $call->external_call_id . ".mp3";
                Storage::disk('public')->put($fileName, $fileContent);
                $call->recording_url = Storage::url($fileName);
                $call->recording_status = 'uploaded';
                $call->save();
                Log::info("Запись для звонка {$call->id} догружена");
            } catch (\Throwable $e) {
                Log::error("Ошибка при догрузке записи для звонка {$call->id}: " . $e->getMessage());
            }
        }

        return 0;
    }

    private function zadarmaGet($path, $params = [], $apiKey = null, $apiSecret = null)
    {
        if (!$apiKey || !$apiSecret) {
            $apiKey = $apiKey ?: env('ZADARMA_API_KEY');
            $apiSecret = $apiSecret ?: env('ZADARMA_API_SECRET');
        }

        ksort($params);
        $queryString = http_build_query($params);

        $signature = base64_encode(
            hash_hmac('sha1', $path . $queryString, $apiSecret, true)
        );

        $url = $path . "?" . $queryString;

        $response = Http::withHeaders([
        'Authorization' => "ZD {$apiKey}:{$signature}"
        ])->get($url);

        return $response;
    }
}
