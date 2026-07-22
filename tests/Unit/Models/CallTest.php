<?php

namespace Tests\Unit\Models;

use App\Models\Call;
use App\Models\Integration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CallTest extends TestCase
{
    use RefreshDatabase;

    public function test_call_belongs_to_integration(): void
    {
        $integration = Integration::create([
            'title' => 'Test integration',
        ]);

        $call = Call::create([
            'integration_id' => $integration->id,
            'external_call_id' => 'call-123',
            'call_time' => now(),
            'direction' => 'in',
            'status' => 'missed',
            'duration' => 0,
            'from_phone' => '+380001112233',
            'to_phone' => '201',
        ]);

        $this->assertTrue($call->integration->is($integration));
        $this->assertTrue($integration->calls->contains($call));
    }
}
