<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Laravel\Passport\Client;

class PassportSeeder extends Seeder
{
    /**
     * Seed Passport OAuth clients.
     */
    public function run(): void
    {
        // Create Personal Access Client
        Client::updateOrCreate(
            [
                'id' => '019c44c9-a118-731d-8271-7ab04a3a32e6',
            ],
            [
                'name' => 'Tike-API',
                'secret' => null,
                'provider' => 'users',
                'redirect_uris' => [],
                'grant_types' => ['personal_access'],
                'revoked' => false,
            ]
        );

        // Create Password Grant Client
        Client::updateOrCreate(
            [
                'id' => '019c44c9-b56d-737e-824e-d4aebf64da41',
            ],
            [
                'name' => 'Tike-API',
                'secret' => '$2y$12$rK61SHuQ7iTeqRVTYRxuN24rRFIZovpKw5SzfBMd',
                'provider' => 'users',
                'redirect_uris' => [],
                'grant_types' => ['password', 'refresh_token'],
                'revoked' => false,
            ]
        );

        $this->command->info('Passport OAuth clients created successfully.');
    }
}
