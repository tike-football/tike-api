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
                'name' => 'admin',
                'role' => 'admin',
                'password' => Hash::make('qwerty123'),
            ]
        );
    }
}
