<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\WithApiKey;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase, WithApiKey;

    public function test_get_token_requires_email_and_password(): void
    {
        $response = $this->postJsonWithApiKey('/api/v1/auth/get-token', []);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => ['email', 'password']
            ]);
    }

    public function test_user_cannot_get_token_with_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'email_verified_at' => now(),
        ]);

        $response = $this->postJsonWithApiKey('/api/v1/auth/get-token', [
            'email' => 'test@example.com',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Your email or password are incorrect.']);
    }

    public function test_verified_user_can_get_token(): void
    {
        // Create personal access client
        $client = Client::create([
            'name' => 'Personal Access Client',
            'secret' => Str::random(40),
            'provider' => null,
            'redirect_uris' => [],
            'grant_types' => ['personal_access'],
            'revoked' => false,
        ]);

        $user = User::factory()->create([
            'email' => 'verified@example.com',
            'password' => bcrypt('password123'),
            'email_verified_at' => now(),
            'role' => 'user',
        ]);

        $response = $this->postJsonWithApiKey('/api/v1/auth/get-token', [
            'email' => 'verified@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['access_token', 'refresh_token']);
    }

    public function test_unverified_user_cannot_get_token(): void
    {
        $user = User::factory()->create([
            'email' => 'unverified@example.com',
            'password' => bcrypt('password123'),
            'email_verified_at' => null,
            'role' => 'user',
        ]);

        $response = $this->postJsonWithApiKey('/api/v1/auth/get-token', [
            'email' => 'unverified@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Your email address is not verified.']);
    }

    public function test_authenticated_user_can_access_scoped_endpoint(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        Passport::actingAs($user, ['test:test']);

        $response = $this->getJsonWithApiKey('/api/v1/scopes/test');

        $response->assertStatus(200)
            ->assertJson([
                'scope' => 'test:test',
                'valid' => true,
            ]);
    }

    public function test_unauthenticated_user_cannot_access_scoped_endpoint(): void
    {
        $response = $this->getJsonWithApiKey('/api/v1/scopes/test');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_user_without_required_scope_cannot_access_endpoint(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        Passport::actingAs($user, ['different:scope']);

        $response = $this->getJsonWithApiKey('/api/v1/scopes/test');

        $response->assertStatus(403);
    }

    public function test_user_role_scopes_are_correctly_assigned(): void
    {
        $adminUser = User::factory()->create(['role' => 'admin']);
        $regularUser = User::factory()->create(['role' => 'user']);

        $adminScopes = config('roles.admin.scopes');
        $userScopes = config('roles.user.scopes');

        $this->assertEquals($adminScopes, $adminUser->getRoleScopes());
        $this->assertEquals($userScopes, $regularUser->getRoleScopes());
    }

    public function test_refresh_token_requires_scope(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['different:scope']);

        $response = $this->postJsonWithApiKey('/api/v1/auth/refresh-token');

        $response->assertStatus(403);
    }

    public function test_refresh_token_requires_api_key(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['user:refresh-token']);

        $response = $this->postJson('/api/v1/auth/refresh-token');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'API key is required.',
            ]);
    }

    public function test_refresh_token_requires_bearer_token(): void
    {
        $response = $this->postJsonWithApiKey('/api/v1/auth/refresh-token');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_refresh_token_returns_new_tokens(): void
    {
        Client::create([
            'name' => 'Personal Access Client',
            'secret' => Str::random(40),
            'provider' => null,
            'redirect_uris' => [],
            'grant_types' => ['personal_access'],
            'revoked' => false,
        ]);

        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['user:refresh-token']);

        $response = $this->postJsonWithApiKey('/api/v1/auth/refresh-token');

        $response->assertStatus(200)
            ->assertJsonStructure(['access_token', 'refresh_token']);
    }
}
