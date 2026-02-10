<?php

namespace Database\Seeders;

use App\Models\ApiKey;
use Illuminate\Database\Seeder;

class ApiKeySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Development keys - FIXED for easy development
        // In production, these should be generated and stored securely
        
        $developmentKeys = [
            [
                'name' => 'iOS App - Development',
                'key' => 'dev_ios_' . str_repeat('a', 56), // dev_ios_aaaaaaaa...
                'platform' => 'ios',
                'rate_limit' => 1000, // Higher limit for dev
                'is_active' => true,
            ],
            [
                'name' => 'Android App - Development',
                'key' => 'dev_android_' . str_repeat('b', 52), // dev_android_bbbb...
                'platform' => 'android',
                'rate_limit' => 1000,
                'is_active' => true,
            ],
            [
                'name' => 'Web App - Development',
                'key' => 'dev_web_' . str_repeat('c', 56), // dev_web_cccccccc...
                'platform' => 'web',
                'rate_limit' => 1000,
                'is_active' => true,
            ],
            [
                'name' => 'Testing - Development',
                'key' => 'dev_test_' . str_repeat('d', 55), // dev_test_ddddddd...
                'platform' => 'testing',
                'rate_limit' => 10000, // Very high for testing
                'is_active' => true,
            ],
        ];

        foreach ($developmentKeys as $keyData) {
            ApiKey::updateOrCreate(
                ['key' => $keyData['key']], // Find by key
                $keyData // Update or create with these values
            );
        }

        $this->command->info('API keys seeded successfully!');
        $this->command->newLine();
        $this->command->info('Development API Keys:');
        $this->command->info('iOS:     ' . $developmentKeys[0]['key']);
        $this->command->info('Android: ' . $developmentKeys[1]['key']);
        $this->command->info('Web:     ' . $developmentKeys[2]['key']);
        $this->command->info('Testing: ' . $developmentKeys[3]['key']);
        $this->command->newLine();
        $this->command->warn('⚠️  These are development keys. Generate new keys for production!');
    }
}
