<?php

namespace Tests\app\Models\TestData;

use Database\Seeders\ProviderSeeder;

trait ProviderTestData
{
    protected function setUpProvider() {
        $this->seed(ProviderSeeder::class);
    }
}
