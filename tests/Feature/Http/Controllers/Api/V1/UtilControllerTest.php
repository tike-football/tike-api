<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Mockery;
use Tests\TestCase;
use Tests\Traits\WithApiKey;

class UtilControllerTest extends TestCase
{
    use RefreshDatabase, WithApiKey;

    public function test_get_available_avatars_requires_api_key(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['util:get']);

        $response = $this->getJson('/api/v1/util/get-available-avatars');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'API key is required.',
            ]);
    }

    public function test_get_available_avatars_requires_bearer_token(): void
    {
        $response = $this->getJsonWithApiKey('/api/v1/util/get-available-avatars');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_get_available_avatars_requires_scope(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['different:scope']);

        $response = $this->getJsonWithApiKey('/api/v1/util/get-available-avatars');

        $response->assertStatus(403);
    }

    public function test_get_available_avatars_returns_local_urls(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['util:get']);

        config()->set('filesystems.folders.user_avatars.driver', 'local');

        $response = $this->getJsonWithApiKey('/api/v1/util/get-available-avatars');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'available_avatars' => [
                    ['avatar_path', 'avatar_url'],
                ],
            ]);

        $firstAvatarPath = (string) config('avatars.options.0');
        $response->assertJsonPath('available_avatars.0.avatar_path', $firstAvatarPath);

        $avatarUrl = $response->json('available_avatars.0.avatar_url');
        $this->assertNotNull($avatarUrl);
        $this->assertStringContainsString('/users/avatars/' . $firstAvatarPath, $avatarUrl);
    }

    public function test_get_available_avatars_signs_urls_when_s3_is_used(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['util:get']);

        config()->set('filesystems.folders.user_avatars.driver', 's3');
        config()->set('filesystems.disks.s3.signed_url_ttl_seconds', 3600);

        $disk = Mockery::mock();
        $disk->shouldReceive('temporaryUrl')
            ->andReturnUsing(function (string $path): string {
                return 'https://signed.example/' . ltrim($path, '/');
            });

        Storage::shouldReceive('disk')
            ->with('s3')
            ->andReturn($disk);

        $response = $this->getJsonWithApiKey('/api/v1/util/get-available-avatars');

        $response->assertStatus(200);

        $firstAvatarPath = (string) config('avatars.options.0');
        $response->assertJsonPath('available_avatars.0.avatar_path', $firstAvatarPath);

        $avatarUrl = $response->json('available_avatars.0.avatar_url');
        $this->assertStringStartsWith('https://signed.example/', (string) $avatarUrl);
        $this->assertStringContainsString('/users/avatars/' . $firstAvatarPath, (string) $avatarUrl);
    }
}
