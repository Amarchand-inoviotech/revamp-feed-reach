<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AttributeTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'Accounts Payable & Procurement'],
            ['name' => 'Accounting & Integrations'],
            ['name' => 'Working Capital'],
        ];
        foreach ($types as $type) {
            \App\Models\AttributeType::create($type);
        }
    }
}
