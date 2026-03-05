<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\User;
use App\Models\UserAvatar;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
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
        $user->setSetting('language', 'es');
        $user->setSetting('theme', 'dark');

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
                    'settings',
                ],
            ])
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.avatar_path', 'system/default01.png')
            ->assertJsonPath('user.settings.language', 'es')
            ->assertJsonPath('user.settings.theme', 'dark');

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

    public function test_user_endpoint_returns_json_when_scope_is_missing_without_accept_header(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
        ]);

        Passport::actingAs($user, ['different:scope']);

        $response = $this->get('/api/v1/user', $this->withApiKeyHeader());

        $response->assertStatus(403)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure([
                'message',
            ]);
    }

    public function test_user_profile_uses_default_avatar_when_avatar_path_is_null(): void
    {
        $user = User::factory()->create([
            'avatar_path' => null,
            'role' => 'user',
        ]);

        Passport::actingAs($user, ['user:get']);

        $response = $this->getJsonWithApiKey('/api/v1/user');

        $response->assertStatus(200)
            ->assertJsonPath('user.avatar_path', 'system/default01.png');

        $avatarUrl = $response->json('user.avatar_url');
        $this->assertNotNull($avatarUrl);
        $this->assertStringContainsString('/users/avatars/system/default01.png', $avatarUrl);
    }

    public function test_update_avatar_requires_api_key(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['user:update']);

        $response = $this->patchJson('/api/v1/user/avatar', [
            'avatar_path' => 'system/default02.png',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'API key is required.',
            ]);
    }

    public function test_update_avatar_requires_bearer_token(): void
    {
        $response = $this->patchJsonWithApiKey('/api/v1/user/avatar', [
            'avatar_path' => 'system/default02.png',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_update_avatar_requires_scope(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['different:scope']);

        $response = $this->patchJsonWithApiKey('/api/v1/user/avatar', [
            'avatar_path' => 'system/default02.png',
        ]);

        $response->assertStatus(403);
    }

    public function test_update_avatar_validates_format(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['user:update']);

        $response = $this->patchJsonWithApiKey('/api/v1/user/avatar', [
            'avatar_path' => 'invalid/path/to/file.png',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar_path']);
    }

    public function test_update_avatar_updates_avatar_path_when_valid(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'avatar_path' => 'system/default01.png',
        ]);
        Passport::actingAs($user, ['user:update']);

        $response = $this->patchJsonWithApiKey('/api/v1/user/avatar', [
            'avatar_path' => 'system/default02.png',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('user.avatar_path', 'system/default02.png');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'avatar_path' => 'system/default02.png',
        ]);
    }

    public function test_update_avatar_rejects_missing_s3_object(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['user:update']);

        config()->set('filesystems.folders.user_avatars.driver', 's3');

        Storage::shouldReceive('disk')
            ->with('s3')
            ->andReturnSelf();
        Storage::shouldReceive('exists')
            ->once()
            ->andReturn(false);

        $response = $this->patchJsonWithApiKey('/api/v1/user/avatar', [
            'avatar_path' => 'system/default02.png',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar_path']);
    }

    public function test_upload_avatar_requires_api_key(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['user:update']);

        $response = $this->post('/api/v1/user/avatar/upload', [
            'avatar' => UploadedFile::fake()->image('avatar.png'),
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'API key is required.',
            ]);
    }

    public function test_upload_avatar_requires_bearer_token(): void
    {
        $response = $this->post('/api/v1/user/avatar/upload', [
            'avatar' => UploadedFile::fake()->image('avatar.png'),
        ], $this->withApiKeyHeader());

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_upload_avatar_requires_scope(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['different:scope']);

        $response = $this->post('/api/v1/user/avatar/upload', [
            'avatar' => UploadedFile::fake()->image('avatar.png'),
        ], $this->withApiKeyHeader());

        $response->assertStatus(403);
    }

    public function test_upload_avatar_stores_path_and_creates_user_avatar(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['user:update']);

        config()->set('filesystems.folders.user_avatars.driver', 'local');
        Storage::fake('local');

        $response = $this->post('/api/v1/user/avatar/upload', [
            'avatar' => UploadedFile::fake()->image('avatar.png'),
        ], $this->withApiKeyHeader());

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'avatar_path',
                    'avatar_url',
                ],
            ]);

        $avatarPath = (string) $response->json('user.avatar_path');
        $this->assertStringStartsWith('users/avatar' . $user->id, $avatarPath);
        $this->assertStringEndsWith('.png', $avatarPath);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'avatar_path' => $avatarPath,
        ]);

        $this->assertDatabaseHas('user_avatars', [
            'user_id' => $user->id,
            'avatar_path' => $avatarPath,
        ]);
    }

    public function test_upload_avatar_keeps_only_latest_three_records(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['user:update']);

        config()->set('filesystems.folders.user_avatars.driver', 'local');
        Storage::fake('local');

        $paths = [];
        $baseTime = Carbon::create(2026, 3, 5, 12, 0, 0);

        for ($i = 0; $i < 4; $i++) {
            Carbon::setTestNow($baseTime->copy()->addMinutes($i));

            $response = $this->post('/api/v1/user/avatar/upload', [
                'avatar' => UploadedFile::fake()->image("avatar{$i}.png"),
            ], $this->withApiKeyHeader());

            $response->assertStatus(200);
            $paths[] = (string) $response->json('user.avatar_path');
        }

        Carbon::setTestNow();

        $this->assertSame(3, UserAvatar::query()->where('user_id', $user->id)->count());

        $remaining = UserAvatar::query()
            ->where('user_id', $user->id)
            ->pluck('avatar_path')
            ->all();

        $this->assertNotContains($paths[0], $remaining);
        $this->assertContains($paths[1], $remaining);
        $this->assertContains($paths[2], $remaining);
        $this->assertContains($paths[3], $remaining);
    }
}
