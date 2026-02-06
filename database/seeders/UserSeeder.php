<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'test@tike.com'],
            [
                'name' => 'Admin',
                'last_name' => 'User',
                'email_verified_at' => now(),
                'country_code' => '+1',
                'phone_number' => '1234567890',
                'role' => 'admin',
                'password' => Hash::make('qwerty123'),
            ]
        );

        // Set admin language to Spanish
        $admin->setSetting('language', 'es');
    }
}
