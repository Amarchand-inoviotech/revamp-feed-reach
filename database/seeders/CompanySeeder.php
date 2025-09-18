<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Company;
use App\Models\Currency;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Company::create([
            'author_id' => 1,
            'author_type' => (new User())->getMorphClass(),
            'country_id' => Currency::where('name', 'USD')->value('id'),
            'locale' => 'en',
            'name' => "Revive Business",
            'domain' => "revivebusiness.com",
            'email' => "nand.lal@inoviotech.com",
            'phone' => "923003779411",
            'logo_id' => null,
            'address' => "Karachi",
            'invoice_url' => "secure.revivebusiness.com/invoice",
        ]);
    }
}
