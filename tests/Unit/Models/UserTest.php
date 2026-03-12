<?php

namespace Tests\Unit\Models;

use App\Models\Friend;
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

    public function test_fake_user_factory_state_generates_expected_active_user_shape(): void
    {
        $user = User::factory()->fakeUser(25)->create();

        $this->assertSame('fakeuser25@test.com', $user->email);
        $this->assertSame('+1', $user->country_code);
        $this->assertSame('7000000025', $user->phone_number);
        $this->assertSame('+17000000025', $user->full_phone_number);
        $this->assertSame('user', $user->role);
        $this->assertNotNull($user->email_verified_at);
        $this->assertContains($user->avatar_path, [
            'system/default01.png',
            'system/default02.png',
            'system/default03.png',
            'system/default04.png',
            'system/default05.png',
            'system/default06.png',
        ]);
    }

    public function test_user_can_detect_sent_friend_request(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Friend::query()->create([
            'user_id' => $user->id,
            'friend_id' => $otherUser->id,
        ]);

        $this->assertTrue($user->hasSentFriendRequestTo($otherUser->id));
        $this->assertFalse($user->hasReceivedFriendRequestFrom($otherUser->id));
        $this->assertFalse($user->isFriendWith($otherUser->id));
    }

    public function test_user_can_detect_received_friend_request(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Friend::query()->create([
            'user_id' => $otherUser->id,
            'friend_id' => $user->id,
        ]);

        $this->assertFalse($user->hasSentFriendRequestTo($otherUser->id));
        $this->assertTrue($user->hasReceivedFriendRequestFrom($otherUser->id));
        $this->assertFalse($user->isFriendWith($otherUser->id));
    }

    public function test_user_can_detect_friendship_when_both_requests_exist(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Friend::query()->create([
            'user_id' => $user->id,
            'friend_id' => $otherUser->id,
        ]);

        Friend::query()->create([
            'user_id' => $otherUser->id,
            'friend_id' => $user->id,
        ]);

        $this->assertTrue($user->hasSentFriendRequestTo($otherUser->id));
        $this->assertTrue($user->hasReceivedFriendRequestFrom($otherUser->id));
        $this->assertTrue($user->isFriendWith($otherUser->id));
    }
}
