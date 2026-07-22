<?php

namespace Tests\Unit\Models;

use App\Models\Integration;
use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_has_integrations(): void
    {
        $provider = Provider::create([
            'name' => Provider::PHONET,
        ]);

        $integration = Integration::create([
            'title' => 'Phonet integration',
            'provider_id' => $provider->id,
        ]);

        $this->assertTrue($integration->provider->is($provider));
        $this->assertTrue($provider->integrations->contains($integration));
    }
}
