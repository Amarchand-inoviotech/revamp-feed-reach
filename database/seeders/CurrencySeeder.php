<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            ['name' => 'USD', 'symbol' => '$'],
            ['name' => 'EUR', 'symbol' => '€'],
            ['name' => 'GBP', 'symbol' => '£'],
            ['name' => 'AUD', 'symbol' => 'A$'],
            ['name' => 'CAD', 'symbol' => 'C$'],
            ['name' => 'JPY', 'symbol' => '¥'],
        ];

        foreach ($data as $item) {
            \App\Models\Currency::create($item);
        }
    }
}
