<?php

namespace Tests\Unit\Services\IntegrationProcess;

use App\Models\Integration;
use App\Services\IntegrationProcess\Phonet\PhonetIntegrationProcessService;
use App\Services\Phonet\PhonetService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\FilesystemManager;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

final class PhonetIntegrationProcessServiceTest extends TestCase
{
    public function test_can_construct_and_return_result_dto(): void
    {
        $service = $this->makeService(); // просто убедимся, что собирается
        $this->assertInstanceOf(PhonetIntegrationProcessService::class, $service);
    }

    public function test_map_skips_download_when_no_audio(): void
    {
        // given: нет audioRecUrl и нет uuid → не будет источника записи
        Storage::fake('public'); // публичный диск
        Http::fake();            // ничего не должно улететь в сеть

        $loggerHandler = new TestHandler();
        $logger = new Logger('test', [$loggerHandler]);

        $service = $this->makeService(logger: $logger);
        $integration = new Integration();
        $integration->id = 1; // достаточно для наших вызовов


        $raw = [
            'uuid' => '',                // важно: пусто, чтобы не подставился публичный fallback
            'lgDirection' => 4,          // inbound
            'endAt' => 1761139188069,    // ms
            'otherLegNum' => '+380944947493',
            'leg' => ['ext' => '201'],
            'duration' => 10,
            // audioRecUrl отсутствует
        ];

        // when
        $mapped = $this->invokeMap($service, $raw, $integration);

        // then
        $this->assertNull($mapped['recording_path']);
        $this->assertNull($mapped['recording_url']);
        $this->assertFalse($loggerHandler->hasWarningRecords()); // не должно быть ошибок
    }

public function test_map_downloads_and_sets_recording_fields(): void
{
    // given: корректный ответ 200 с mp3 (>1KB)
    Storage::fake('public');

    // NEW: заранее создаём ожидаемый файл, чтобы сработала идемпотентность
    $expectedPath = 'records/phonet/2025/10/uuid-ok.mp3';
    Storage::disk('public')->put($expectedPath, str_repeat('A', 2048));

    $body = str_repeat('A', 2048);
    Http::fake([
        'https://phonet.test/rest/public/*' => Http::response($body, 200, ['Content-Type' => 'audio/mpeg']),
        '*' => Http::response('not found', 404),
    ]);

    $loggerHandler = new TestHandler();
    $logger = new Logger('test', [$loggerHandler]);

    $service = $this->makeService(logger: $logger);

    // Интеграция без фабрики
    $integration = new \App\Models\Integration();
    $integration->id = 1;
    $integration->domain = 'phonet.test';

    $raw = [
        'uuid'        => 'uuid-ok',
        'lgDirection' => 2,                 // outbound
        'dialAt'      => 1761139188069,     // 2025-10-22 → Y/m = 2025/10
        'otherLegNum' => '+123456',
        'leg'         => ['ext' => '201'],
        'billSecs'    => 8,
        // можно оставить пустым — маппер соберёт публичный URL сам,
        // либо явно указать публичный endpoint:
        'audioRecUrl' => 'https://phonet.test/rest/public/calls/uuid-ok/audio',
    ];

    // when
    $mapped = $this->invokeMap($service, $raw, $integration);

    // then
    $this->assertIsString($mapped['recording_path']);
    $this->assertSame($expectedPath, $mapped['recording_path']); // NEW: уточняем проверку
    $this->assertIsString($mapped['recording_url']);
    $this->assertNotSame('', $mapped['recording_url']);
    $this->assertFalse($loggerHandler->hasWarningRecords());
}


    public function test_map_handles_download_error_without_failing(): void
    {
        // given: три 5xx подряд → упадёт скачивание внутри и должен быть warning
        Storage::fake('public');

        Http::fake([
            'phonet.test/*' => Http::sequence()
                ->push('err', 500)
                ->push('err', 502)
                ->push('err', 503),
            '*' => Http::response('not found', 404),
        ]);

        $loggerHandler = new TestHandler();
        $logger = new Logger('test', [$loggerHandler]);

        $service = $this->makeService(logger: $logger);
        $integration = new Integration();
        $integration->id = 1; // достаточно для наших вызовов


        $raw = [
            'uuid' => 'uuid-fail',
            'lgDirection' => 4, // inbound
            'endAt' => 1761139188069,
            'otherLegNum' => '+111',
            'leg' => ['ext' => '222'],
            'duration' => 5,
            'audioRecUrl' => 'https://phonet.test/audio/uuid-fail.mp3',
        ];

        // when
        $mapped = $this->invokeMap($service, $raw, $integration);

        // then
        $this->assertNull($mapped['recording_path']);
        $this->assertNull($mapped['recording_url']);
        $this->assertTrue($loggerHandler->hasWarningThatContains('Phonet recording'));
    }

    /**
     * Сборка реального сервиса с подменой зависимостей.
     */
    private function makeService(?LoggerInterface $logger = null): PhonetIntegrationProcessService
    {
        /** @var FilesystemManager $fs */
        $fs = app('filesystem');

        /** @var PhonetService $phonet */
        $phonet = $this->createMock(PhonetService::class);

        $logger ??= new Logger('test');

        return new PhonetIntegrationProcessService(
            $phonet,
            $fs,
            $logger
        );
    }

    /**
     * Вызывает приватный mapPhonetCall(array $raw, Integration $integration) через Reflection.
     *
     * @return array
     */
    private function invokeMap(PhonetIntegrationProcessService $service, array $raw, Integration $integration): array
    {
        $ref = new \ReflectionClass($service);
        $method = $ref->getMethod('mapPhonetCall');
        $method->setAccessible(true);
        /** @var array $res */
        $res = $method->invoke($service, $raw, $integration);
        return $res;
    }
}
