<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::create([
            'company_id' => 1,
            'name'    => 'Guest User',
            'username' => 'guest_user',
            'email' => 'guest01@inoviotech.com',
            'avatar_id'    => null,
            'gender'    => 'M',
            'dob'   => "1990-01-01",
            'phone' => '+923003779411',
            'two_factor'    => null,
            'notification'  => null,
            'password'  => 'secret',
            'remember_token' => Str::random(10),
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);
        User::factory()->count(10)->create();
    }
}
