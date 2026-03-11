<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Friend;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\WithApiKey;

class FriendControllerTest extends TestCase
{
    use RefreshDatabase, WithApiKey;

    public function test_add_requires_api_key(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $friend = User::factory()->create();
        Passport::actingAs($user, ['friend:add']);

        $response = $this->postJson('/api/v1/friend/add/' . $friend->id);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'API key is required.',
            ]);
    }

    public function test_add_requires_bearer_token(): void
    {
        $friend = User::factory()->create();

        $response = $this->postJsonWithApiKey('/api/v1/friend/add/' . $friend->id);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_add_requires_scope(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $friend = User::factory()->create();
        Passport::actingAs($user, ['different:scope']);

        $response = $this->postJsonWithApiKey('/api/v1/friend/add/' . $friend->id);

        $response->assertStatus(403);
    }

    public function test_add_returns_friend_request_sent_when_other_user_has_not_added_back(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $friend = User::factory()->create();
        Passport::actingAs($user, ['friend:add']);

        $response = $this->postJsonWithApiKey('/api/v1/friend/add/' . $friend->id);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Friend request sent.',
            ]);

        $this->assertDatabaseHas('friends', [
            'user_id' => $user->id,
            'friend_id' => $friend->id,
        ]);
    }

    public function test_add_returns_friend_added_when_other_user_already_added_auth_user(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $friend = User::factory()->create();
        Passport::actingAs($user, ['friend:add']);

        Friend::query()->create([
            'user_id' => $friend->id,
            'friend_id' => $user->id,
        ]);

        $response = $this->postJsonWithApiKey('/api/v1/friend/add/' . $friend->id);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Friend added.',
            ]);

        $this->assertDatabaseHas('friends', [
            'user_id' => $user->id,
            'friend_id' => $friend->id,
        ]);
    }

    public function test_add_returns_request_already_sent_when_request_exists_from_auth_user(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $friend = User::factory()->create();
        Passport::actingAs($user, ['friend:add']);

        Friend::query()->create([
            'user_id' => $user->id,
            'friend_id' => $friend->id,
        ]);

        $response = $this->postJsonWithApiKey('/api/v1/friend/add/' . $friend->id);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Friend request has already been sent.',
            ]);

        $this->assertSame(1, Friend::query()
            ->where('user_id', $user->id)
            ->where('friend_id', $friend->id)
            ->count());
    }

    public function test_add_returns_already_friends_when_both_records_exist(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $friend = User::factory()->create();
        Passport::actingAs($user, ['friend:add']);

        Friend::query()->create([
            'user_id' => $user->id,
            'friend_id' => $friend->id,
        ]);

        Friend::query()->create([
            'user_id' => $friend->id,
            'friend_id' => $user->id,
        ]);

        $response = $this->postJsonWithApiKey('/api/v1/friend/add/' . $friend->id);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'You are already friends.',
            ]);

        $this->assertSame(1, Friend::query()
            ->where('user_id', $user->id)
            ->where('friend_id', $friend->id)
            ->count());

        $this->assertSame(1, Friend::query()
            ->where('user_id', $friend->id)
            ->where('friend_id', $user->id)
            ->count());
    }

    public function test_search_requires_api_key(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['friend:search']);

        $response = $this->getJson('/api/v1/friend/search/abel');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'API key is required.',
            ]);
    }

    public function test_search_requires_bearer_token(): void
    {
        $response = $this->getJsonWithApiKey('/api/v1/friend/search/abel');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_search_requires_scope(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['different:scope']);

        $response = $this->getJsonWithApiKey('/api/v1/friend/search/abel');

        $response->assertStatus(403);
    }

    public function test_search_returns_empty_when_term_is_shorter_than_three_characters(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['friend:search']);

        User::factory()->create([
            'name' => 'Abel',
            'last_name' => 'Test',
            'email_verified_at' => now(),
        ]);

        $response = $this->getJsonWithApiKey('/api/v1/friend/search/ab');

        $response->assertStatus(200)
            ->assertExactJson([
                'users' => [],
            ]);
    }

    public function test_search_returns_exact_verified_email_match_only(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['friend:search']);

        $target = User::factory()->create([
            'name' => 'Abel',
            'last_name' => 'Dev',
            'email' => 'friend@test.com',
            'email_verified_at' => now(),
        ]);

        User::factory()->create([
            'name' => 'Friend',
            'last_name' => 'Other',
            'email' => 'other@test.com',
            'email_verified_at' => now(),
        ]);

        $response = $this->getJsonWithApiKey('/api/v1/friend/search/friend@test.com');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'users')
            ->assertJsonPath('users.0.id', $target->id)
            ->assertJsonMissingPath('users.0.email')
            ->assertJsonMissingPath('users.0.phone_number');
    }

    public function test_search_returns_exact_verified_phone_match_only(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['friend:search']);

        $target = User::factory()->create([
            'name' => 'Abel',
            'last_name' => 'Phone',
            'country_code' => '+1',
            'phone_number' => '5551234567',
            'full_phone_number' => '+15551234567',
            'email_verified_at' => now(),
        ]);

        User::factory()->create([
            'name' => 'Another',
            'last_name' => 'User',
            'email_verified_at' => now(),
        ]);

        $response = $this->getJsonWithApiKey('/api/v1/friend/search/15551234567');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'users')
            ->assertJsonPath('users.0.id', $target->id);
    }

    public function test_search_returns_partial_matches_by_name_or_last_name_only_for_verified_users(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['friend:search']);

        $matchByName = User::factory()->create([
            'name' => 'Carlos',
            'last_name' => 'Abelardo',
            'email_verified_at' => now(),
        ]);

        $matchByLastName = User::factory()->create([
            'name' => 'Luis',
            'last_name' => 'Abel',
            'email_verified_at' => now(),
        ]);

        User::factory()->unverified()->create([
            'name' => 'Abel',
            'last_name' => 'Hidden',
        ]);

        $response = $this->getJsonWithApiKey('/api/v1/friend/search/Abel');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'users');

        $ids = collect($response->json('users'))->pluck('id')->all();

        $this->assertContains($matchByName->id, $ids);
        $this->assertContains($matchByLastName->id, $ids);
    }

    public function test_search_limits_partial_results_to_ten_users(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['friend:search']);

        User::factory()->count(12)->create([
            'name' => 'Mario',
            'last_name' => 'Searchable',
            'email_verified_at' => now(),
        ]);

        $response = $this->getJsonWithApiKey('/api/v1/friend/search/Searchable');

        $response->assertStatus(200)
            ->assertJsonCount(10, 'users');
    }

    public function test_search_matches_concatenated_name_and_last_name_case_insensitive(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['friend:search']);

        $target = User::factory()->create([
            'name' => 'Abel',
            'last_name' => 'Rojas',
            'email_verified_at' => now(),
        ]);

        User::factory()->create([
            'name' => 'Abel',
            'last_name' => 'Hidden',
            'email_verified_at' => null,
        ]);

        $response = $this->getJsonWithApiKey('/api/v1/friend/search/ABEL ROJAS');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'users')
            ->assertJsonPath('users.0.id', $target->id)
            ->assertJsonPath('users.0.name', 'Abel')
            ->assertJsonPath('users.0.last_name', 'Rojas');
    }
}
