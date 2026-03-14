<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Friend;
use App\Models\Group;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\WithApiKey;

class GroupControllerTest extends TestCase
{
    use RefreshDatabase, WithApiKey;

    private function fakePngUpload(string $name = 'group.png'): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4////fwAJ+wP9KobjigAAAABJRU5ErkJggg==',
            true
        );

        return UploadedFile::fake()->createWithContent($name, $png === false ? '' : $png);
    }

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

    public function test_update_requires_scope(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
        ]);
        $group = Group::query()->create([
            'owner_id' => $user->id,
            'name' => 'Tike Group',
            'language' => 'es',
        ]);

        Passport::actingAs($user, ['different:scope']);

        $response = $this->patchJsonWithApiKey('/api/v1/group/' . $group->id, [
            'name' => 'Updated Group',
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
            ->assertJsonPath('group.image_path', null)
            ->assertJsonPath('group.image_url', null)
            ->assertJsonPath('group.language', 'en')
            ->assertJsonPath('group.is_active', true)
            ->assertJsonPath('group.allows_comments', false)
            ->assertJsonPath('group.accepts_join_requests', true)
            ->assertJsonPath('group.requires_join_approval', false)
            ->assertJsonPath('group.total_users', 1)
            ->assertJsonPath('group.is_owner', true);

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

    public function test_update_requires_owner(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $otherUser = User::factory()->create(['role' => 'user']);
        $group = Group::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Tike Group',
            'language' => 'es',
        ]);

        Passport::actingAs($otherUser, ['group:add']);

        $response = $this->patchJsonWithApiKey('/api/v1/group/' . $group->id, [
            'name' => 'Updated Group',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'You cannot update this group.',
            ]);
    }

    public function test_update_updates_allowed_fields_and_returns_group_response(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $member = User::factory()->create(['role' => 'user']);
        $group = Group::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Tike Group',
            'description' => 'General chat',
            'image_path' => 'group120260313235319.png',
            'language' => 'es',
        ]);

        $group->users()->attach($owner->id, ['is_accepted' => true]);
        $group->users()->attach($member->id, ['is_accepted' => true]);

        Passport::actingAs($owner, ['group:add']);

        $response = $this->patchJsonWithApiKey('/api/v1/group/' . $group->id, [
            'name' => 'Updated Group',
            'description' => 'Updated description',
            'is_active' => false,
            'allows_comments' => true,
            'accepts_join_requests' => false,
            'requires_join_approval' => true,
            'language' => 'en',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('group.id', $group->id)
            ->assertJsonPath('group.owner_id', $owner->id)
            ->assertJsonPath('group.name', 'Updated Group')
            ->assertJsonPath('group.description', 'Updated description')
            ->assertJsonPath('group.image_path', 'group120260313235319.png')
            ->assertJsonPath('group.is_active', false)
            ->assertJsonPath('group.allows_comments', true)
            ->assertJsonPath('group.accepts_join_requests', false)
            ->assertJsonPath('group.requires_join_approval', true)
            ->assertJsonPath('group.language', 'en')
            ->assertJsonPath('group.total_users', 2)
            ->assertJsonPath('group.is_owner', true);

        $this->assertStringContainsString('group120260313235319.png', (string) $response->json('group.image_url'));

        $this->assertDatabaseHas('groups', [
            'id' => $group->id,
            'name' => 'Updated Group',
            'description' => 'Updated description',
            'is_active' => false,
            'allows_comments' => true,
            'accepts_join_requests' => false,
            'requires_join_approval' => true,
            'language' => 'en',
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

    public function test_users_requires_group_membership(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $outsider = User::factory()->create(['role' => 'user']);

        $group = Group::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Tike Group',
            'language' => 'es',
        ]);

        $group->users()->attach($owner->id, ['is_accepted' => true]);

        Passport::actingAs($outsider, ['group:get']);

        $response = $this->getJsonWithApiKey('/api/v1/group/' . $group->id . '/users');

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'You do not belong to this group.',
            ]);
    }

    public function test_users_returns_group_members_with_avatar_and_friend_status(): void
    {
        $authUser = User::factory()->create(['role' => 'user']);
        $friendUser = User::factory()->create([
            'role' => 'user',
            'name' => 'Abel',
            'last_name' => 'Rippin',
            'avatar_path' => 'system/default02.png',
        ]);
        $outgoingUser = User::factory()->create([
            'role' => 'user',
            'name' => 'Bruno',
            'last_name' => 'Stone',
        ]);
        $incomingUser = User::factory()->create([
            'role' => 'user',
            'name' => 'Carlos',
            'last_name' => 'Mills',
        ]);

        $group = Group::query()->create([
            'owner_id' => $authUser->id,
            'name' => 'Tike Group',
            'language' => 'es',
        ]);

        $group->users()->attach($authUser->id, ['is_accepted' => true]);
        $group->users()->attach($friendUser->id, ['is_accepted' => true]);
        $group->users()->attach($outgoingUser->id, ['is_accepted' => true]);
        $group->users()->attach($incomingUser->id, ['is_accepted' => true]);

        Friend::query()->create([
            'user_id' => $authUser->id,
            'friend_id' => $friendUser->id,
        ]);
        Friend::query()->create([
            'user_id' => $friendUser->id,
            'friend_id' => $authUser->id,
        ]);
        Friend::query()->create([
            'user_id' => $authUser->id,
            'friend_id' => $outgoingUser->id,
        ]);
        Friend::query()->create([
            'user_id' => $incomingUser->id,
            'friend_id' => $authUser->id,
        ]);

        Passport::actingAs($authUser, ['group:get']);

        $response = $this->getJsonWithApiKey('/api/v1/group/' . $group->id . '/users');

        $response->assertStatus(200)
            ->assertJsonPath('group.id', $group->id)
            ->assertJsonPath('group.owner_id', $authUser->id)
            ->assertJsonPath('group.name', 'Tike Group')
            ->assertJsonPath('group.description', null)
            ->assertJsonPath('group.image_path', null)
            ->assertJsonPath('group.image_url', null)
            ->assertJsonPath('group.is_active', true)
            ->assertJsonPath('group.allows_comments', false)
            ->assertJsonPath('group.accepts_join_requests', true)
            ->assertJsonPath('group.requires_join_approval', false)
            ->assertJsonPath('group.language', 'es')
            ->assertJsonPath('group.total_users', 4)
            ->assertJsonPath('group.is_owner', true)
            ->assertJsonPath('total_users', 4)
            ->assertJsonCount(4, 'users')
            ->assertJsonFragment([
                'id' => $friendUser->id,
                'name' => 'Abel',
                'last_name' => 'Rippin',
                'avatar_url' => url('/storage/users/avatars/system/default02.png'),
                'status' => 'friend',
            ])
            ->assertJsonFragment([
                'id' => $authUser->id,
                'name' => $authUser->name,
                'last_name' => $authUser->last_name,
                'status' => null,
            ])
            ->assertJsonFragment([
                'id' => $outgoingUser->id,
                'name' => 'Bruno',
                'last_name' => 'Stone',
                'status' => 'outgoing_friend_request',
            ])
            ->assertJsonFragment([
                'id' => $incomingUser->id,
                'name' => 'Carlos',
                'last_name' => 'Mills',
                'status' => 'incoming_friend_request',
            ]);
    }

    public function test_index_requires_group_get_scope(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['different:scope']);

        $response = $this->getJsonWithApiKey('/api/v1/group');

        $response->assertStatus(403);
    }

    public function test_index_returns_groups_where_authenticated_user_is_accepted_member(): void
    {
        $authUser = User::factory()->create(['role' => 'user']);
        $otherOwner = User::factory()->create(['role' => 'user']);

        $ownedGroup = Group::query()->create([
            'owner_id' => $authUser->id,
            'name' => 'Owned Group',
            'description' => 'Owned description',
            'image_path' => 'owned.png',
            'language' => 'es',
        ]);

        $memberGroup = Group::query()->create([
            'owner_id' => $otherOwner->id,
            'name' => 'Member Group',
            'description' => 'Member description',
            'image_path' => 'member.png',
            'language' => 'en',
        ]);

        $pendingGroup = Group::query()->create([
            'owner_id' => $otherOwner->id,
            'name' => 'Pending Group',
            'language' => 'es',
        ]);

        $ownedGroup->users()->attach($authUser->id, ['is_accepted' => true]);
        $ownedGroup->users()->attach($otherOwner->id, ['is_accepted' => true]);

        $memberGroup->users()->attach($authUser->id, ['is_accepted' => true]);
        $memberGroup->users()->attach($otherOwner->id, ['is_accepted' => true]);

        $pendingGroup->users()->attach($authUser->id, ['is_accepted' => false]);

        Passport::actingAs($authUser, ['group:get']);

        $response = $this->getJsonWithApiKey('/api/v1/group');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'groups')
            ->assertJsonFragment([
                'id' => $memberGroup->id,
                'name' => 'Member Group',
                'description' => 'Member description',
                'image_path' => 'member.png',
                'language' => 'en',
                'total_users' => 2,
                'is_owner' => false,
            ])
            ->assertJsonFragment([
                'id' => $ownedGroup->id,
                'name' => 'Owned Group',
                'description' => 'Owned description',
                'image_path' => 'owned.png',
                'language' => 'es',
                'total_users' => 2,
                'is_owner' => true,
            ]);

        $response->assertJsonMissing([
            'id' => $pendingGroup->id,
        ]);
    }

    public function test_upload_image_requires_group_owner(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create(['role' => 'user']);
        $otherUser = User::factory()->create(['role' => 'user']);
        $group = Group::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Tike Group',
            'language' => 'es',
        ]);

        $group->users()->attach($owner->id, ['is_accepted' => true]);

        Passport::actingAs($otherUser, ['group:add']);

        $response = $this->withHeaders($this->withApiKeyHeader())
            ->post('/api/v1/group/' . $group->id . '/image/upload', [
                'image' => $this->fakePngUpload(),
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'You cannot update the image of this group.',
            ]);
    }

    public function test_upload_image_updates_group_image_and_returns_group_response(): void
    {
        Storage::fake('local');

        config()->set('filesystems.default', 'local');
        config()->set('filesystems.folders.group_images.driver', 'local');
        config()->set('filesystems.folders.group_images.root', 'groups/images/');

        $owner = User::factory()->create(['role' => 'user']);
        $member = User::factory()->create(['role' => 'user']);
        $group = Group::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Tike Group',
            'description' => 'General chat',
            'language' => 'es',
        ]);

        $group->users()->attach($owner->id, ['is_accepted' => true]);
        $group->users()->attach($member->id, ['is_accepted' => true]);

        Passport::actingAs($owner, ['group:add']);

        $response = $this->withHeaders($this->withApiKeyHeader())
            ->post('/api/v1/group/' . $group->id . '/image/upload', [
                'image' => $this->fakePngUpload(),
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('group.id', $group->id)
            ->assertJsonPath('group.owner_id', $owner->id)
            ->assertJsonPath('group.name', 'Tike Group')
            ->assertJsonPath('group.description', 'General chat')
            ->assertJsonPath('group.total_users', 2);

        $imagePath = $response->json('group.image_path');
        $this->assertIsString($imagePath);
        $this->assertNotSame('', $imagePath);
        $this->assertStringContainsString($imagePath, (string) $response->json('group.image_url'));

        $this->assertDatabaseHas('groups', [
            'id' => $group->id,
            'image_path' => $imagePath,
        ]);

        Storage::disk('local')->assertExists('groups/images/' . $imagePath);
    }
}
