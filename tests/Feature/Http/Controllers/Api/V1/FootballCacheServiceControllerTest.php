<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\WithApiKey;

class FootballCacheServiceControllerTest extends TestCase
{
    use RefreshDatabase, WithApiKey;

    public function test_cache_fixtures_requires_api_key(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Passport::actingAs($admin, ['football-data:cache']);

        $response = $this->postJson('/api/v1/admin/football-data/cache-fixtures');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'API key is required.',
            ]);
    }

    public function test_cache_fixtures_requires_bearer_token(): void
    {
        $response = $this->postJsonWithApiKey('/api/v1/admin/football-data/cache-fixtures');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_cache_fixtures_requires_scope(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Passport::actingAs($admin, ['different:scope']);

        $response = $this->postJsonWithApiKey('/api/v1/admin/football-data/cache-fixtures');

        $response->assertStatus(403);
    }

    public function test_admin_with_scope_can_run_cache_fixtures_command(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Passport::actingAs($admin, ['football-data:cache']);

        Artisan::shouldReceive('call')
            ->once()
            ->with('football-data:cache-fixtures')
            ->andReturn(0);

        Artisan::shouldReceive('output')
            ->once()
            ->andReturn("Fixtures cache generated.\n");

        $response = $this->postJsonWithApiKey('/api/v1/admin/football-data/cache-fixtures');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Fixtures cache command executed successfully.',
                'command' => 'football-data:cache-fixtures',
                'exit_code' => 0,
                'output' => 'Fixtures cache generated.',
            ]);
    }

    public function test_cache_fixtures_returns_500_when_command_fails(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Passport::actingAs($admin, ['football-data:cache']);

        Artisan::shouldReceive('call')
            ->once()
            ->with('football-data:cache-fixtures')
            ->andThrow(new \RuntimeException('Command exploded'));

        $response = $this->postJsonWithApiKey('/api/v1/admin/football-data/cache-fixtures');

        $response->assertStatus(500)
            ->assertJson([
                'message' => 'An error occurred while caching fixtures.',
                'error' => 'Fixtures cache command failed. Please try again.',
            ]);
    }
}
