<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_has_correct_scopes(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $scopes = $user->getRoleScopes();

        $this->assertIsArray($scopes);
        $this->assertContains('test:test', $scopes);
    }

    public function test_regular_user_has_correct_scopes(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
        ]);

        $scopes = $user->getRoleScopes();

        $this->assertIsArray($scopes);
        $this->assertContains('test:test', $scopes);
    }

    public function test_user_with_unknown_role_returns_empty_scopes(): void
    {
        $user = User::factory()->create([
            'role' => 'unknown_role',
        ]);

        $scopes = $user->getRoleScopes();

        $this->assertIsArray($scopes);
        $this->assertEmpty($scopes);
    }

    public function test_all_configured_roles_have_scopes(): void
    {
        $roles = config('roles');

        $this->assertNotEmpty($roles);

        foreach ($roles as $roleName => $roleConfig) {
            $user = User::factory()->create([
                'role' => $roleName,
            ]);

            $scopes = $user->getRoleScopes();

            $this->assertIsArray($scopes);
            $this->assertEquals($roleConfig['scopes'], $scopes, "Role {$roleName} should have correct scopes");
        }
    }

    public function test_role_scopes_match_configuration(): void
    {
        $adminScopes = config('roles.admin.scopes');
        $userScopes = config('roles.user.scopes');

        $adminUser = User::factory()->create(['role' => 'admin']);
        $regularUser = User::factory()->create(['role' => 'user']);

        $this->assertEquals($adminScopes, $adminUser->getRoleScopes());
        $this->assertEquals($userScopes, $regularUser->getRoleScopes());
    }
}
