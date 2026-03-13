<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Group;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\WithApiKey;

class GroupControllerTest extends TestCase
{
    use RefreshDatabase, WithApiKey;

    public function test_store_requires_api_key(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
        ]);

        Passport::actingAs($user, ['group:add']);

        $response = $this->postJson('/api/v1/group', [
            'name' => 'Tike Group',
            'description' => 'General chat',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'API key is required.',
            ]);
    }

    public function test_store_requires_bearer_token(): void
    {
        $response = $this->postJsonWithApiKey('/api/v1/group', [
            'name' => 'Tike Group',
            'description' => 'General chat',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_store_requires_scope(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
        ]);

        Passport::actingAs($user, ['different:scope']);

        $response = $this->postJsonWithApiKey('/api/v1/group', [
            'name' => 'Tike Group',
            'description' => 'General chat',
        ]);

        $response->assertStatus(403);
    }

    public function test_store_validates_required_name(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
        ]);

        Passport::actingAs($user, ['group:add']);

        $response = $this->postJsonWithApiKey('/api/v1/group', [
            'description' => 'General chat',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'The group name is required.',
            ]);
    }

    public function test_store_creates_group_with_user_language_and_attaches_owner(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
        ]);

        Setting::query()->create([
            'user_id' => $user->id,
            'key' => 'language',
            'value' => 'en',
        ]);

        Passport::actingAs($user, ['group:add']);

        $response = $this->postJsonWithApiKey('/api/v1/group', [
            'name' => 'Tike Group',
            'description' => 'General chat',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('group.owner_id', $user->id)
            ->assertJsonPath('group.name', 'Tike Group')
            ->assertJsonPath('group.description', 'General chat')
            ->assertJsonPath('group.language', 'en')
            ->assertJsonPath('group.is_active', true)
            ->assertJsonPath('group.allows_comments', false)
            ->assertJsonPath('group.accepts_join_requests', true)
            ->assertJsonPath('group.requires_join_approval', false);

        $groupId = $response->json('group.id');

        $this->assertDatabaseHas('groups', [
            'id' => $groupId,
            'owner_id' => $user->id,
            'name' => 'Tike Group',
            'description' => 'General chat',
            'language' => 'en',
        ]);

        $this->assertDatabaseHas('group_user', [
            'group_id' => $groupId,
            'user_id' => $user->id,
            'is_accepted' => true,
        ]);
    }

    public function test_add_users_requires_owner(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $otherUser = User::factory()->create(['role' => 'user']);
        $group = Group::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Tike Group',
            'language' => 'es',
        ]);

        Passport::actingAs($otherUser, ['group:add']);

        $response = $this->postJsonWithApiKey('/api/v1/group/' . $group->id . '/users', [
            'user_ids' => [$owner->id],
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'You cannot add users to this group.',
            ]);
    }

    public function test_add_users_validates_user_ids_as_integer_array(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $group = Group::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Tike Group',
            'language' => 'es',
        ]);

        Passport::actingAs($owner, ['group:add']);

        $response = $this->postJsonWithApiKey('/api/v1/group/' . $group->id . '/users', [
            'user_ids' => ['abc'],
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Each user id must be an integer.',
            ]);
    }

    public function test_add_users_adds_users_and_returns_errors(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $userToAdd = User::factory()->create(['role' => 'user']);
        $existingUser = User::factory()->create(['role' => 'user']);
        $missingUserId = 999999;

        $group = Group::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Tike Group',
            'language' => 'es',
        ]);

        $group->users()->attach($owner->id, ['is_accepted' => true]);
        $group->users()->attach($existingUser->id, ['is_accepted' => true]);

        Passport::actingAs($owner, ['group:add']);

        $response = $this->postJsonWithApiKey('/api/v1/group/' . $group->id . '/users', [
            'user_ids' => [$userToAdd->id, $existingUser->id, $missingUserId],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Users processed successfully.',
                'added_user_ids' => [$userToAdd->id],
            ])
            ->assertJsonPath('errors.0.id', $existingUser->id)
            ->assertJsonPath('errors.0.error', 'User already belongs to the group.')
            ->assertJsonPath('errors.1.id', $missingUserId)
            ->assertJsonPath('errors.1.error', 'User does not exist.');

        $this->assertDatabaseHas('group_user', [
            'group_id' => $group->id,
            'user_id' => $userToAdd->id,
            'is_accepted' => true,
        ]);
    }
}
