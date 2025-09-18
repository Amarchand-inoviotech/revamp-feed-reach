<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [
                'name' => 'paypal',
                'meta' => [
                    'client_id',
                    'client_secret',
                ]
            ],
            [
                'name' => 'stripe',
                'meta'=> [
                    'publishable_key',
                    'secret_key',
                    'webhook_secret',
                ]

            ],
            [
                'name' => 'nmi',
                'meta' => [
                    'secret_api_key',
                    'public_api_key',
                    'webhook_secret',
                    "vault_enabled",
                ]
            ],
            [
                'name' => 'cybersource',
                "meta" => [
                    'merchant_id',
                    'api_key',
                    'api_secret_key',
                ]
            ],
            [
                'name' => 'cybersource_card',
                'meta'=> [
                    'merchant_id',
                    'api_key',
                    'api_secret_key'
                ]
            ],
            [
                'name' => 'authorize.net',
                "meta" => [
                    'api_login_id',
                    'transaction_key',
                ]
            ],
            [
                'name' => 'square',
                'meta'=> [
                    'access_token',
                    'location_id',
                ]
            ],

        ];

        foreach ($data as $item) {
            PaymentMethod::create($item);
        }
    }
}
