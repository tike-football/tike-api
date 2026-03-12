<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\WithApiKey;

class AdminCommandControllerTest extends TestCase
{
    use RefreshDatabase, WithApiKey;

    public function test_create_fake_users_requires_api_key(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        Passport::actingAs($admin, ['admin:run-commands']);

        $response = $this->postJson('/api/v1/admin/command/create-fake-users/100');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'API key is required.',
            ]);
    }

    public function test_create_fake_users_requires_bearer_token(): void
    {
        $response = $this->postJsonWithApiKey('/api/v1/admin/command/create-fake-users/100');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_create_fake_users_requires_scope(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        Passport::actingAs($admin, ['different:scope']);

        $response = $this->postJsonWithApiKey('/api/v1/admin/command/create-fake-users/100');

        $response->assertStatus(403);
    }

    public function test_create_fake_users_runs_command(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        Passport::actingAs($admin, ['admin:run-commands']);

        Artisan::shouldReceive('call')
            ->once()
            ->with('users:create-fake', ['count' => 100])
            ->andReturn(0);

        $response = $this->postJsonWithApiKey('/api/v1/admin/command/create-fake-users/100');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Command executed successfully.',
                'command' => 'users:create-fake',
                'count' => 100,
            ]);
    }

    public function test_create_fake_users_validates_count(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        Passport::actingAs($admin, ['admin:run-commands']);

        $response = $this->postJsonWithApiKey('/api/v1/admin/command/create-fake-users/0');

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'The count must be greater than 0.',
            ]);
    }
}
