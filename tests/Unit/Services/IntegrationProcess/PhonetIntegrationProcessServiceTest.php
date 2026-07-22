<?php

namespace Tests\Unit\Services\IntegrationProcess;

use App\DTO\IntegrationProcess\IntegrationProcessDownloadStatusDTO;
use App\Models\Integration;
use App\Services\IntegrationProcess\Phonet\PhonetIntegrationProcessService;
use App\Services\Notification\NotificationService;
use App\Services\OpenAi\OpenAIService;
use App\Services\Phonet\PhonetService;
use App\Services\Telegram\TelegramService;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

final class PhonetIntegrationProcessServiceTest extends TestCase
{
    private PhonetIntegrationProcessService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PhonetIntegrationProcessService(
            $this->createMock(PhonetService::class),
            $this->createMock(OpenAIService::class),
            $this->createMock(TelegramService::class),
            $this->createMock(NotificationService::class),
        );
    }

    public function test_service_can_be_constructed(): void
    {
        $this->assertInstanceOf(PhonetIntegrationProcessService::class, $this->service);
    }

    #[DataProvider('invalidCallPayloads')]
    public function test_invalid_call_payload_is_skipped(array $payload): void
    {
        $integration = new Integration();
        $integration->id = 1;

        $result = $this->invokePrivate(
            'processIntegrationPbxCallStat',
            [$integration, $payload],
        );

        $this->assertNull($result);
    }

    public static function invalidCallPayloads(): array
    {
        return [
            'empty payload' => [[]],
            'missing UUID' => [[
                'lgDirection' => 4,
            ]],
            'unsupported direction' => [[
                'uuid' => 'call-123',
                'lgDirection' => 1,
            ]],
        ];
    }

    public function test_recording_without_uuid_returns_empty_download_status(): void
    {
        $integration = new Integration();

        $result = $this->invokePrivate(
            'downloadAndSavePhonetRecord',
            [$integration, []],
        );

        $this->assertInstanceOf(IntegrationProcessDownloadStatusDTO::class, $result);
        $this->assertNull($result->recording);
        $this->assertNull($result->recordingStatus);
    }

    private function invokePrivate(string $methodName, array $arguments): mixed
    {
        $method = (new ReflectionClass($this->service))->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($this->service, $arguments);
    }
}
