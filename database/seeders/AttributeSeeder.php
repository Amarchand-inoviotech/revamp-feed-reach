<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AttributeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $attributes = [
            [
                'attribute_type_id' => 1,
                'content' => 'Accounts Payable & Procurement',
            ],
            [
                'attribute_type_id' => 1,
                'content' => 'Accounting & Integrations',
            ],
            [
                'attribute_type_id' => 1,
                'content' => 'Working Capital',
            ],
            [
                'attribute_type_id' => 2,
                'content' => 'Accounts Payable & Procurement',
            ],
            [
                'attribute_type_id' => 2,
                'content' => 'Accounting & Integrations',
            ],
            [
                'attribute_type_id' => 2,
                'content' => 'Working Capital',
            ],
            [
                'attribute_type_id' => 3,
                'content' => 'Accounts Payable & Procurement',
            ],
            [
                'attribute_type_id' => 3,
                'content' => 'Accounting & Integrations',
            ],
            [
                'attribute_type_id' => 3,
                'content' => 'Working Capital',
            ],
        ];
        foreach ($attributes as $attribute) {
            \App\Models\Attribute::create($attribute);
        }

        $attributes = \App\Models\Attribute::all();
        $packages = \App\Models\Package::all();

        $attributes->each(function ($attribute) use ($packages) {
            $ids = $packages->random(rand(1, 3))->pluck('id')->toArray();
            $attribute->packages()->attach(
                $ids
            );
        });
    }
}
