<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

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
}
