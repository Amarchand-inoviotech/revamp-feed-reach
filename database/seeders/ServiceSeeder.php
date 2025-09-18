<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = [
            [
                'name' => 'LLC Registration',
            ],
            [
                'name'=> 'S-Corporation',
            ],
            [
                'name'=> 'C-Corporation',
            ],
            [
                'name'=> 'Non-Profit',
            ]

        ];
        foreach ($services as $service) {
            $service['author_id'] = 1;
            $service['author_type'] = (new \App\Models\User())->getMorphClass();
            Service::create($service);
        }
    }
}
