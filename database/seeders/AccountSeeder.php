<?php

namespace Database\Seeders;

use App\Helpers\Encrypt;
use App\Models\Account;
use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = [
            //NMI
            [
                'author_type' => (new \App\Models\User())->getMorphClass(),
                'author_id' => 1,
                'payment_method_id' => PaymentMethod::where('name', 'nmi')->value('id'), // NMI
                'company_id' => 1,
                'name' => 'Maxis-MARKETING SOLUTIONS HUB',
                "descriptor" => "Maxis-MARKETING SOLUTIONS HUB",
                'email' => 'nand.php@gmail.com',
                'daily_limit' => 10000,
                'monthly_limit' => 300000,
                'status' => true,
                'meta' => [
                     'sandbox' => [
                        'secret_api_key' =>Encrypt::encrypt('42H5hVC4xkYf97m89bj6b48SBH688xSY'),
                        'public_api_key' =>Encrypt::encrypt('rfS89f-T2KTDe-Fk6vme-98uYs8'),
                        'webhook_secret' => Encrypt::encrypt('5m4DgqN4ni8ey1lMITyhTPfhuOjIY3KC-Y0lo6QhGiTZeUNzGPfgEWTfqmPC7H2kn1HA7uHP6EKzYWCD--sN_hkxkxFxlPv5'),
                        'vault_enabled' => 1,
                    ],
                    'live' => [
                        'secret_api_key' => Encrypt::encrypt('42H5hVC4xkYf97m89bj6b48SBH688xSY'),
                        'public_api_key' => Encrypt::encrypt('rfS89f-T2KTDe-Fk6vme-98uYs8'),
                        'webhook_secret' => Encrypt::encrypt('5m4DgqN4ni8ey1lMITyhTPfhuOjIY3KC-Y0lo6QhGiTZeUNzGPfgEWTfqmPC7H2kn1HA7uHP6EKzYWCD--sN_hkxkxFxlPv5'),
                        'vault_enabled' => 1,
                    ]
                ],
            ]
        ];



        foreach ($accounts as $account) {
            Account::create($account);
        }
    }
}
