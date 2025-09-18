<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Enum\UserGaurdEnum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Auth::loginUsingId(1);

        $this->call([
            CurrencySeeder::class,
            CountrySeeder::class,
            CompanySeeder::class,
            ServiceSeeder::class,
            PaymentMethodSeeder::class,
            PackageSeeder::class,
            UserSeeder::class,
            RolePermissionSeeder::class,
            StatusSeeder::class,
            AttributeTypeSeeder::class,
            AttributeSeeder::class,
            AccountSeeder::class,
        ]);
    }
}
