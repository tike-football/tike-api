<?php

namespace Tests\Feature\Http\Middleware;

use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidateApiKeyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test API key
        ApiKey::create([
            'name' => 'Test API Key',
            'key' => 'test_key_123456789',
            'platform' => 'testing',
            'is_active' => true,
            'rate_limit' => 100,
        ]);
    }

    public function test_request_without_api_key_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/countries');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'API key is required.',
                'error' => 'Missing X-API-Key header'
            ]);
    }

    public function test_request_with_invalid_api_key_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/countries', [
            'X-API-Key' => 'invalid_key_12345'
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid API key.',
                'error' => 'The provided API key is not valid'
            ]);
    }

    public function test_request_with_valid_api_key_is_accepted(): void
    {
        $response = $this->getJson('/api/v1/countries', [
            'X-API-Key' => 'test_key_123456789'
        ]);

        $response->assertStatus(200);
    }

    public function test_request_with_inactive_api_key_is_rejected(): void
    {
        $apiKey = ApiKey::where('key', 'test_key_123456789')->first();
        $apiKey->update(['is_active' => false]);

        $response = $this->getJson('/api/v1/countries', [
            'X-API-Key' => 'test_key_123456789'
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'API key is inactive or expired.'
            ]);
    }

    public function test_request_with_expired_api_key_is_rejected(): void
    {
        $apiKey = ApiKey::where('key', 'test_key_123456789')->first();
        $apiKey->update(['expires_at' => now()->subDay()]);

        $response = $this->getJson('/api/v1/countries', [
            'X-API-Key' => 'test_key_123456789'
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'API key is inactive or expired.'
            ]);
    }

    public function test_health_endpoint_does_not_require_api_key(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok'
            ]);
    }

    public function test_api_key_last_used_at_is_updated(): void
    {
        $apiKey = ApiKey::where('key', 'test_key_123456789')->first();
        $this->assertNull($apiKey->last_used_at);

        $this->getJson('/api/v1/countries', [
            'X-API-Key' => 'test_key_123456789'
        ]);

        // Give time for the async job to complete
        sleep(1);

        $apiKey->refresh();
        $this->assertNotNull($apiKey->last_used_at);
    }

    public function test_sign_up_endpoint_requires_api_key(): void
    {
        $response = $this->postJson('/api/v1/auth/sign-up', [
            'name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'country_code' => '+1',
            'phone_number' => '1234567890',
            'password' => 'TestPass123',
            'password_confirmation' => 'TestPass123',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'API key is required.'
            ]);
    }

    public function test_get_token_endpoint_requires_api_key(): void
    {
        $response = $this->postJson('/api/v1/auth/get-token', [
            'email' => 'test@tike.com',
            'password' => 'qwerty123',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'API key is required.'
            ]);
    }

    public function test_development_api_keys_are_seeded(): void
    {
        // Run seeder
        $this->seed(\Database\Seeders\ApiKeySeeder::class);

        $this->assertDatabaseHas('api_keys', [
            'platform' => 'ios',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('api_keys', [
            'platform' => 'android',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('api_keys', [
            'platform' => 'web',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('api_keys', [
            'platform' => 'testing',
            'is_active' => true,
        ]);
    }
}
