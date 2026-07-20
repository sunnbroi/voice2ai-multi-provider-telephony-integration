<?php

namespace Tests\App\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\app\Models\TestData\ProviderTestData;
use Tests\TestCase;
/**
 * @group skip-temp
 */
class CallTest extends TestCase
{
    use RefreshDatabase;
    use ProviderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpProvider();
    }

    public function test_belongs_to_integration()
    {

    }
}
