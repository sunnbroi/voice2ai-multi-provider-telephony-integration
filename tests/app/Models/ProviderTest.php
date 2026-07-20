<?php

namespace Tests\App\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\app\Models\TestData\ProviderTestData;
use Tests\TestCase;
/**
 * @group skip-temp
 */
class ProviderTest extends TestCase
{
    use RefreshDatabase;
    use ProviderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpProvider();
    }

    public function test_exists_binotel()
    {
        $this->assertDatabaseHas('providers', [
            'name' => 'Binotel',
        ]);
    }
}
