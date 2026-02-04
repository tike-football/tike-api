<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_token_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/v1/auth/get-token', []);

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
        ]);

        $response = $this->postJson('/api/v1/auth/get-token', [
            'email' => 'test@example.com',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Your email or password are incorrect.']);
    }

    public function test_authenticated_user_can_access_scoped_endpoint(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        Passport::actingAs($user, ['test:test']);

        $response = $this->getJson('/api/v1/scopes/test');

        $response->assertStatus(200)
            ->assertJson([
                'scope' => 'test:test',
                'valid' => true,
            ]);
    }

    public function test_unauthenticated_user_cannot_access_scoped_endpoint(): void
    {
        $response = $this->getJson('/api/v1/scopes/test');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_user_without_required_scope_cannot_access_endpoint(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        Passport::actingAs($user, ['different:scope']);

        $response = $this->getJson('/api/v1/scopes/test');

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
}
