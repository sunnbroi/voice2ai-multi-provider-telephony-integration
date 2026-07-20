<?php

namespace Database\Seeders;

use App\Models\Provider;
use Illuminate\Database\Seeder;

class ProviderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $providers = [
            'binotel',
            'Zadarma',
            'Unitalk',
            'Phonet',
        ];

        foreach ($providers as $name) {
            Provider::firstOrCreate(['name' => $name]);
        }
    }
}
