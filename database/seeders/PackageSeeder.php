<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $packages = [
            [
                "name"=> "Pro",
                "price"=> "9900",
                "billing_cycle"=> "yearly",
                "is_agent"=> false,
            ],
            [
                "name"=> "Company",
                "price"=> "19900",
                "billing_cycle"=> "yearly",
                "is_agent"=> true,
            ],
            [
                "name"=> "Enterprise",
                "price"=> "29900",
                "billing_cycle"=> "yearly",
                "is_agent"=> true,
            ],
             [
                "name"=> "Custom Pacakage",
                "price"=> "0",
                "billing_cycle"=> "monthly",
                "is_agent"=> true,
            ]
        ] ;

        foreach ($packages as $package) {
            $package['author_id'] = 1;
            $package['author_type'] = (new \App\Models\User())->getMorphClass();
           $package = \App\Models\Package::create(attributes: $package);

           if($package['name'] == 'Custom Pacakage'){
            \App\Models\GatewayPackage::create([
                'payment_method_id' => PaymentMethod::where('name', 'nmi')->value('id'),
                'package_id' => $package->id,
                'gateway_id' => "no_need_to_gateway_plan",
            ]);
           }
        }
    }
}
