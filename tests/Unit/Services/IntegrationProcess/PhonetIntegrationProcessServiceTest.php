<?php

namespace Tests\Unit\Services\IntegrationProcess;

use App\DTO\IntegrationProcess\IntegrationProcessDownloadStatusDTO;
use App\Models\Integration;
use App\Services\IntegrationProcess\Phonet\PhonetIntegrationProcessService;
use App\Services\Notification\NotificationService;
use App\Services\OpenAi\OpenAIService;
use App\Services\Phonet\PhonetService;
use App\Services\Telegram\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

final class PhonetIntegrationProcessServiceTest extends TestCase
{
    use RefreshDatabase;

    private PhonetService $phonetService;

    private PhonetIntegrationProcessService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->phonetService = $this->createMock(PhonetService::class);

        $this->service = new PhonetIntegrationProcessService(
            $this->phonetService,
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

    public function test_process_incoming_missed_call_creates_normalized_call(): void
    {
        $integration = Integration::create([
            'title' => 'Phonet test integration',
            'notify_type' => 'missed',
            'active' => true,
        ]);

        $this->phonetService
            ->expects($this->once())
            ->method('getCallsHistory')
            ->willReturn([
                'count' => 1,
                'calls' => [[
                    'uuid' => 'phonet-call-123',
                    'lgDirection' => 4,
                    'endAt' => 1_761_139_188_069,
                    'otherLegNum' => '+38 (094) 494-74-93',
                    'leg' => [
                        'ext' => '201',
                        'displayName' => 'Alice',
                    ],
                    'disposition' => 1,
                    'billSecs' => 0,
                ]],
            ]);

        $result = $this->service->processIntegration($integration);

        $this->assertNotNull($result);
        $this->assertSame(1, $result->callsCount);
        $this->assertSame(1, $result->created);
        $this->assertSame(0, $result->updated);
        $this->assertSame(0, $result->notified);

        $this->assertDatabaseHas('calls', [
            'integration_id' => $integration->id,
            'external_call_id' => 'phonet-call-123',
            'direction' => 'in',
            'status' => 'missed',
            'from_phone' => '+380944947493',
            'to_phone' => '201',
            'duration' => 0,
            'operator_name' => 'Alice',
        ]);
    }

    private function invokePrivate(string $methodName, array $arguments): mixed
    {
        $method = (new ReflectionClass($this->service))->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($this->service, $arguments);
    }
}
