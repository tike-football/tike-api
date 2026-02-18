<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\WithApiKey;

class UserControllerTest extends TestCase
{
    use RefreshDatabase, WithApiKey;

    public function test_authenticated_user_with_scope_can_get_profile(): void
    {
        $user = User::factory()->create([
            'avatar_path' => 'system/default01.png',
            'role' => 'user',
        ]);

        Passport::actingAs($user, ['user:get']);

        $response = $this->getJsonWithApiKey('/api/v1/user');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'last_name',
                    'email',
                    'country_code',
                    'phone_number',
                    'full_phone_number',
                    'role',
                    'avatar_path',
                    'avatar_url',
                ],
            ])
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.avatar_path', 'system/default01.png');

        $avatarUrl = $response->json('user.avatar_url');
        $this->assertNotNull($avatarUrl);
        $this->assertStringContainsString('/users/avatars/system/default01.png', $avatarUrl);
    }

    public function test_profile_response_does_not_include_sensitive_or_timestamp_fields(): void
    {
        $user = User::factory()->create([
            'avatar_path' => 'system/default01.png',
            'email_verified_at' => now(),
            'role' => 'user',
        ]);

        Passport::actingAs($user, ['user:get']);

        $response = $this->getJsonWithApiKey('/api/v1/user');

        $response->assertStatus(200)
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('user.email_verified_at')
            ->assertJsonMissingPath('user.created_at')
            ->assertJsonMissingPath('user.updated_at');
    }

    public function test_user_endpoint_requires_api_key(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
        ]);

        Passport::actingAs($user, ['user:get']);

        $response = $this->getJson('/api/v1/user');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'API key is required.',
            ]);
    }

    public function test_user_endpoint_requires_bearer_token(): void
    {
        $response = $this->getJsonWithApiKey('/api/v1/user');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_user_endpoint_requires_user_get_scope(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
        ]);

        Passport::actingAs($user, ['different:scope']);

        $response = $this->getJsonWithApiKey('/api/v1/user');

        $response->assertStatus(403);
    }
}
