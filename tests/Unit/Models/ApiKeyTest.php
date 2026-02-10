<?php

namespace Tests\Unit\Models;

use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_generate_api_key(): void
    {
        $apiKey = ApiKey::generate('Test Key', 'testing', 100);

        $this->assertInstanceOf(ApiKey::class, $apiKey);
        $this->assertEquals('Test Key', $apiKey->name);
        $this->assertEquals('testing', $apiKey->platform);
        $this->assertEquals(100, $apiKey->rate_limit);
        $this->assertTrue($apiKey->is_active);
        $this->assertEquals(64, strlen($apiKey->key));
    }

    public function test_generated_key_is_unique(): void
    {
        $key1 = ApiKey::generate('Key 1', 'ios', 100);
        $key2 = ApiKey::generate('Key 2', 'android', 100);

        $this->assertNotEquals($key1->key, $key2->key);
    }

    public function test_is_valid_returns_true_for_active_key(): void
    {
        $apiKey = ApiKey::generate('Test Key', 'testing', 100);

        $this->assertTrue($apiKey->isValid());
    }

    public function test_is_valid_returns_false_for_inactive_key(): void
    {
        $apiKey = ApiKey::generate('Test Key', 'testing', 100);
        $apiKey->update(['is_active' => false]);

        $this->assertFalse($apiKey->isValid());
    }

    public function test_is_valid_returns_false_for_expired_key(): void
    {
        $apiKey = ApiKey::generate('Test Key', 'testing', 100);
        $apiKey->update(['expires_at' => now()->subDay()]);

        $this->assertFalse($apiKey->isValid());
    }

    public function test_is_valid_returns_true_for_non_expired_key(): void
    {
        $apiKey = ApiKey::generate('Test Key', 'testing', 100);
        $apiKey->update(['expires_at' => now()->addMonth()]);

        $this->assertTrue($apiKey->isValid());
    }

    public function test_mark_as_used_updates_last_used_at(): void
    {
        $apiKey = ApiKey::generate('Test Key', 'testing', 100);
        $this->assertNull($apiKey->last_used_at);

        $apiKey->markAsUsed();
        $apiKey->refresh();

        $this->assertNotNull($apiKey->last_used_at);
        $this->assertTrue($apiKey->last_used_at->isToday());
    }

    public function test_active_scope_returns_only_valid_keys(): void
    {
        // Create active key
        $activeKey = ApiKey::generate('Active Key', 'testing', 100);

        // Create inactive key
        $inactiveKey = ApiKey::generate('Inactive Key', 'testing', 100);
        $inactiveKey->update(['is_active' => false]);

        // Create expired key
        $expiredKey = ApiKey::generate('Expired Key', 'testing', 100);
        $expiredKey->update(['expires_at' => now()->subDay()]);

        $activeKeys = ApiKey::active()->get();

        $this->assertCount(1, $activeKeys);
        $this->assertEquals($activeKey->id, $activeKeys->first()->id);
    }

    public function test_generate_key_creates_64_character_string(): void
    {
        $key = ApiKey::generateKey();

        $this->assertEquals(64, strlen($key));
        // Str::random generates alphanumeric characters
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{64}$/', $key);
    }
}
