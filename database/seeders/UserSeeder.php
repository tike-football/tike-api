<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'test@tike.com'],
            [
                'name' => 'Admin',
                'last_name' => 'User',
                'country_code' => '+1',
                'phone_number' => '1234567890',
                'role' => 'admin',
                'password' => Hash::make('qwerty123'),
            ]
        );
    }
}
