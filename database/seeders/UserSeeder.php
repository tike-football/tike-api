<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $countryCode = '+1';
        $phoneNumber = '1234567890';

        $admin = User::updateOrCreate(
            ['email' => 'test@tike.com'],
            [
                'name' => 'Admin',
                'last_name' => 'User',
                'email_verified_at' => now(),
                'country_code' => $countryCode,
                'phone_number' => $phoneNumber,
                'full_phone_number' => $countryCode . $phoneNumber,
                'role' => 'admin',
                'avatar_path' => 'system/default01.png',
                'password' => Hash::make('qwerty123'),
            ]
        );

        // Set admin language to Spanish
        $admin->setSetting('language', 'es');
    }
}
