<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Fixture;
use App\Models\Group;
use App\Models\League;
use App\Models\LeagueSeason;
use App\Models\Pool;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\WithApiKey;

class PoolControllerTest extends TestCase
{
    use RefreshDatabase, WithApiKey;

    public function test_store_requires_api_key(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['pool:add']);

        $response = $this->postJson('/api/v1/pool', []);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'API key is required.',
            ]);
    }

    public function test_index_requires_scope(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['different:scope']);

        $response = $this->getJsonWithApiKey('/api/v1/pool');

        $response->assertStatus(403);
    }

    public function test_show_requires_scope(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $pool = Pool::query()->create([
            'owner_id' => $user->id,
            'name' => 'Pool',
            'description' => str_repeat('Descripcion valida. ', 8),
            'scope' => 'league',
            'type' => 'league_general',
            'status' => 'scheduled',
        ]);
        Passport::actingAs($user, ['different:scope']);

        $response = $this->getJsonWithApiKey('/api/v1/pool/' . $pool->id);

        $response->assertStatus(403);
    }

    public function test_store_requires_bearer_token(): void
    {
        $response = $this->postJsonWithApiKey('/api/v1/pool', []);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_store_requires_scope(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['different:scope']);

        $response = $this->postJsonWithApiKey('/api/v1/pool', []);

        $response->assertStatus(403);
    }

    public function test_update_requires_scope(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $pool = Pool::query()->create([
            'owner_id' => $user->id,
            'name' => 'Pool',
            'description' => str_repeat('Descripcion valida. ', 8),
            'scope' => 'league',
            'type' => 'league_general',
            'status' => 'draft',
        ]);

        Passport::actingAs($user, ['different:scope']);

        $response = $this->postJsonWithApiKey('/api/v1/pool/' . $pool->id, [
            'pool' => [
                'name' => 'Updated Pool',
                'description' => str_repeat('Descripcion valida. ', 8),
                'scope' => 'league',
                'type' => 'league_general',
                'score_repeat_limit' => 0,
                'accepts_join_requests' => true,
                'requires_join_approval' => false,
                'requires_join_code' => false,
                'is_active' => false,
                'user_is' => [$user->id],
            ],
        ]);

        $response->assertStatus(403);
    }

    public function test_join_requires_scope(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $pool = Pool::query()->create([
            'owner_id' => $user->id,
            'name' => 'Pool',
            'description' => str_repeat('Descripcion valida. ', 8),
            'scope' => 'league',
            'type' => 'league_general',
            'status' => 'draft',
        ]);

        Passport::actingAs($user, ['different:scope']);

        $response = $this->postJsonWithApiKey('/api/v1/pool/' . $pool->id . '/join', []);

        $response->assertStatus(403);
    }

    public function test_review_join_request_requires_scope(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $requestUser = User::factory()->create(['role' => 'user']);
        $pool = Pool::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Pool',
            'description' => str_repeat('Descripcion valida. ', 8),
            'scope' => 'league',
            'type' => 'league_general',
            'status' => 'scheduled',
        ]);

        \App\Models\PoolUser::query()->create([
            'pool_id' => $pool->id,
            'user_id' => $requestUser->id,
            'approved' => false,
        ]);

        Passport::actingAs($owner, ['different:scope']);

        $response = $this->postJsonWithApiKey('/api/v1/pool/' . $pool->id . '/review-join-request', [
            'user_id' => $requestUser->id,
            'approved' => true,
        ]);

        $response->assertStatus(403);
    }

    public function test_store_validates_required_fields(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['pool:add']);

        $response = $this->postJsonWithApiKey('/api/v1/pool', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'name',
                'description',
                'scope',
                'type',
            ]);
    }

    public function test_store_requires_league_season_when_league_id_is_present(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $league = $this->createLeague();
        Passport::actingAs($user, ['pool:add']);

        $response = $this->postJsonWithApiKey('/api/v1/pool', [
            'league_id' => $league->id,
            'name' => 'Pool de prueba',
            'description' => str_repeat('Descripcion valida. ', 8),
            'scope' => 'league',
            'type' => 'league_general',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['league_season_id']);
    }

    public function test_store_requires_fixture_id_and_valid_type_for_match_scope(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['pool:add']);

        $response = $this->postJsonWithApiKey('/api/v1/pool', [
            'name' => 'Pool de partido',
            'description' => str_repeat('Descripcion valida. ', 8),
            'scope' => 'match',
            'type' => 'invalid_type',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'fixture_id',
                'type',
            ]);
    }

    public function test_store_requires_match_fixture_to_be_upcoming_and_start_after_one_hour(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $league = $this->createLeague();
        $leagueSeason = $this->createLeagueSeason($league);
        $fixture = $this->createFixture($league, (int) $leagueSeason->year, [
            'fixture_date' => now()->addMinutes(30),
            'timestamp' => now()->addMinutes(30)->timestamp,
            'status_long' => 'First Half',
            'status_short' => '1H',
        ]);

        Passport::actingAs($user, ['pool:add']);

        $response = $this->postJsonWithApiKey('/api/v1/pool', [
            'league_id' => $league->id,
            'league_season_id' => $leagueSeason->id,
            'name' => 'Pool de partido',
            'description' => str_repeat('Descripcion valida. ', 8),
            'scope' => 'match',
            'fixture_id' => $fixture->id,
            'type' => 'selected_score',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fixture_id'])
            ->assertJsonPath(
                'errors.fixture_id.0',
                'The fixture must be an upcoming fixture and must not start within the next hour.'
            );
    }

    public function test_store_creates_inactive_match_pool_and_related_pool_fixture(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $league = $this->createLeague();
        $leagueSeason = $this->createLeagueSeason($league);
        $fixture = $this->createFixture($league, (int) $leagueSeason->year);

        Passport::actingAs($user, ['pool:add']);

        $response = $this->postJsonWithApiKey('/api/v1/pool', [
            'league_id' => $league->id,
            'league_season_id' => $leagueSeason->id,
            'name' => 'Pool de partido',
            'description' => str_repeat('Descripcion valida. ', 8),
            'scope' => 'match',
            'fixture_id' => $fixture->id,
            'type' => 'selected_score',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('pool.owner_id', $user->id)
            ->assertJsonPath('pool.league_id', $league->id)
            ->assertJsonPath('pool.league_season_id', $leagueSeason->id)
            ->assertJsonPath('pool.group_id', null)
            ->assertJsonPath('pool.name', 'Pool de partido')
            ->assertJsonPath('pool.scope', 'match')
            ->assertJsonPath('pool.type', 'selected_score')
            ->assertJsonPath('pool.status', 'draft')
            ->assertJsonPath('pool.match.id', $fixture->id)
            ->assertJsonPath('pool.match.league_id', $league->id)
            ->assertJsonPath('pool.match.season', (int) $leagueSeason->year)
            ->assertJsonPath('pool.match.round', 'Regular Season - 1')
            ->assertJsonPath('pool.match.status', 'upcoming')
            ->assertJsonPath('pool.match.status_short', 'NS')
            ->assertJsonPath('pool.match.home_team_id', $fixture->home_team_id)
            ->assertJsonPath('pool.match.away_team_id', $fixture->away_team_id)
            ->assertJsonPath('pool.match.score.home', null)
            ->assertJsonPath('pool.match.score.away', null)
            ->assertJsonPath('pool.possible_scores.s00.0', 0)
            ->assertJsonPath('pool.possible_scores.s00.1', 0)
            ->assertJsonPath('pool.possible_scores.s99.0', 9)
            ->assertJsonPath('pool.possible_scores.s99.1', 9)
            ->assertJsonPath('pool.possible_score_ids.0', 's00')
            ->assertJsonPath('pool.possible_score_ids.99', 's99')
            ->assertJsonPath('pool.is_active', false);

        $poolId = $response->json('pool.id');

        $this->assertDatabaseHas('pools', [
            'id' => $poolId,
            'owner_id' => $user->id,
            'league_id' => $league->id,
            'league_season_id' => $leagueSeason->id,
            'name' => 'Pool de partido',
            'scope' => 'match',
            'type' => 'selected_score',
            'status' => 'draft',
            'is_active' => false,
            'accepts_join_requests' => true,
            'requires_join_approval' => false,
        ]);

        $this->assertDatabaseHas('pool_fixtures', [
            'pool_id' => $poolId,
            'fixture_id' => $fixture->id,
            'allows_repeated_scores' => false,
            'score_selection_type' => 'selected_score',
        ]);
    }

    public function test_store_creates_non_match_pool_without_pool_fixture(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $member = User::factory()->create([
            'role' => 'user',
            'name' => 'Alexandrea',
            'last_name' => 'Stokes',
            'avatar_path' => 'system/default01.png',
        ]);
        $league = $this->createLeague();
        $leagueSeason = $this->createLeagueSeason($league);
        $group = Group::query()->create([
            'owner_id' => $user->id,
            'name' => 'Pool Group',
            'language' => 'es',
        ]);

        $group->users()->attach($user->id, ['is_accepted' => true]);
        $group->users()->attach($member->id, ['is_accepted' => true]);

        Passport::actingAs($user, ['pool:add']);

        $response = $this->postJsonWithApiKey('/api/v1/pool', [
            'league_id' => $league->id,
            'league_season_id' => $leagueSeason->id,
            'group_id' => $group->id,
            'name' => 'Pool de liga',
            'description' => str_repeat('Descripcion valida. ', 8),
            'scope' => 'league',
            'type' => 'league_general',
            'accepts_join_requests' => false,
            'requires_join_approval' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('pool.scope', 'league')
            ->assertJsonPath('pool.type', 'league_general')
            ->assertJsonPath('pool.status', 'draft')
            ->assertJsonCount(2, 'pool.users')
            ->assertJsonPath('pool.is_active', false);

        $response->assertJsonFragment([
            'id' => $member->id,
            'name' => 'Alexandrea',
            'last_name' => 'Stokes',
            'avatar_url' => url('/storage/users/avatars/system/default01.png'),
        ]);

        $poolId = $response->json('pool.id');

        $this->assertDatabaseHas('pools', [
            'id' => $poolId,
            'accepts_join_requests' => false,
            'requires_join_approval' => true,
        ]);

        $this->assertDatabaseMissing('pool_fixtures', [
            'pool_id' => $poolId,
        ]);
    }

    public function test_update_updates_pool_pool_fixture_pool_users_and_pool_user_fixtures(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $firstUser = User::factory()->create([
            'role' => 'user',
            'name' => 'Alexandrea',
            'last_name' => 'Stokes',
            'avatar_path' => 'system/default01.png',
        ]);
        $secondUser = User::factory()->create([
            'role' => 'user',
            'name' => 'Bridgette',
            'last_name' => 'Prohaska',
            'avatar_path' => 'system/default01.png',
        ]);
        $league = $this->createLeague();
        $leagueSeason = $this->createLeagueSeason($league);
        $fixture = $this->createFixture($league, (int) $leagueSeason->year);
        $pool = Pool::query()->create([
            'owner_id' => $owner->id,
            'league_id' => $league->id,
            'league_season_id' => $leagueSeason->id,
            'name' => 'Pool original',
            'description' => str_repeat('Descripcion valida. ', 8),
            'scope' => 'match',
            'type' => 'selected_score',
            'status' => 'draft',
            'is_active' => false,
        ]);

        \App\Models\PoolFixture::query()->create([
            'pool_id' => $pool->id,
            'fixture_id' => $fixture->id,
            'allows_repeated_scores' => false,
            'score_repeat_limit' => 0,
            'score_selection_type' => 'selected_score',
        ]);

        Passport::actingAs($owner, ['pool:add']);

        $response = $this->postJsonWithApiKey('/api/v1/pool/' . $pool->id, [
            'pool' => [
                'name' => 'Pool de prueba',
                'description' => 'Esta es una descripcion de prueba suficientemente larga para cumplir con la validacion minima de cien caracteres en la creacion de una pool.',
                'scope' => 'match',
                'type' => 'random_score',
                'score_repeat_limit' => 0,
                'accepts_join_requests' => true,
                'requires_join_approval' => false,
                'requires_join_code' => true,
                'is_active' => false,
                'user_is' => [$firstUser->id, $secondUser->id],
                'possible_score_ids' => ['s00', 's01', 's02', 's20'],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('pool.name', 'Pool de prueba')
            ->assertJsonPath('pool.type', 'random_score')
            ->assertJsonPath('pool.status', 'scheduled')
            ->assertJsonPath('pool.accepts_join_requests', true)
            ->assertJsonPath('pool.requires_join_approval', false)
            ->assertJsonPath('pool.code', fn ($code) => is_string($code) && strlen($code) === 6)
            ->assertJsonPath('pool.possible_score_ids.0', 's00')
            ->assertJsonPath('pool.possible_scores.s00.0', 0)
            ->assertJsonPath('pool.possible_scores.s20.0', 2)
            ->assertJsonCount(2, 'pool.users')
            ->assertJsonPath('pool.users.0.id', $firstUser->id)
            ->assertJsonPath('pool.users.1.id', $secondUser->id);

        $this->assertDatabaseHas('pools', [
            'id' => $pool->id,
            'name' => 'Pool de prueba',
            'type' => 'random_score',
            'status' => 'scheduled',
            'accepts_join_requests' => true,
            'requires_join_approval' => false,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('pool_fixtures', [
            'pool_id' => $pool->id,
            'fixture_id' => $fixture->id,
            'allows_repeated_scores' => false,
            'score_repeat_limit' => 0,
            'score_selection_type' => 'random_score',
        ]);

        $this->assertSame(2, \App\Models\PoolUser::query()->where('pool_id', $pool->id)->count());
        $this->assertSame(2, \App\Models\PoolUserFixture::query()->where('pool_id', $pool->id)->count());

        $this->assertDatabaseHas('pool_user_fixtures', [
            'pool_id' => $pool->id,
            'user_id' => $firstUser->id,
            'fixture_id' => $fixture->id,
            'home_goals' => null,
            'away_goals' => null,
            'entry_order' => null,
            'is_locked' => false,
        ]);
    }

    public function test_join_adds_user_when_pool_has_matching_code(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $joiner = User::factory()->create(['role' => 'user']);
        $league = $this->createLeague();
        $leagueSeason = $this->createLeagueSeason($league);
        $fixture = $this->createFixture($league, (int) $leagueSeason->year);
        $pool = Pool::query()->create([
            'owner_id' => $owner->id,
            'league_id' => $league->id,
            'league_season_id' => $leagueSeason->id,
            'name' => 'Pool',
            'description' => str_repeat('Descripcion valida. ', 8),
            'scope' => 'match',
            'type' => 'selected_score',
            'status' => 'scheduled',
            'code' => 'ABC123',
        ]);

        \App\Models\PoolFixture::query()->create([
            'pool_id' => $pool->id,
            'fixture_id' => $fixture->id,
            'score_selection_type' => 'selected_score',
        ]);

        Passport::actingAs($joiner, ['pool:join']);

        $response = $this->postJsonWithApiKey('/api/v1/pool/' . $pool->id . '/join', [
            'code' => 'ABC123',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'You have joined the pool successfully.',
            ]);

        $this->assertDatabaseHas('pool_users', [
            'pool_id' => $pool->id,
            'user_id' => $joiner->id,
            'approved' => true,
        ]);

        $this->assertDatabaseHas('pool_user_fixtures', [
            'pool_id' => $pool->id,
            'user_id' => $joiner->id,
            'fixture_id' => $fixture->id,
            'is_locked' => false,
        ]);
    }

    public function test_join_returns_error_when_code_does_not_match(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $joiner = User::factory()->create(['role' => 'user']);
        $pool = Pool::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Pool',
            'description' => str_repeat('Descripcion valida. ', 8),
            'scope' => 'league',
            'type' => 'league_general',
            'status' => 'scheduled',
            'code' => 'ABC123',
        ]);

        Passport::actingAs($joiner, ['pool:join']);

        $response = $this->postJsonWithApiKey('/api/v1/pool/' . $pool->id . '/join', [
            'code' => 'ZZZ999',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'The provided join code is invalid.',
            ]);
    }

    public function test_join_creates_pending_record_when_approval_is_required(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $joiner = User::factory()->create(['role' => 'user']);
        $pool = Pool::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Pool',
            'description' => str_repeat('Descripcion valida. ', 8),
            'scope' => 'league',
            'type' => 'league_general',
            'status' => 'scheduled',
            'requires_join_approval' => true,
            'accepts_join_requests' => true,
        ]);

        Passport::actingAs($joiner, ['pool:join']);

        $response = $this->postJsonWithApiKey('/api/v1/pool/' . $pool->id . '/join', []);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Your join request has been sent and is pending approval.',
            ]);

        $this->assertDatabaseHas('pool_users', [
            'pool_id' => $pool->id,
            'user_id' => $joiner->id,
            'approved' => false,
        ]);
    }

    public function test_join_returns_message_if_user_is_already_in_pool(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $joiner = User::factory()->create(['role' => 'user']);
        $pool = Pool::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Pool',
            'description' => str_repeat('Descripcion valida. ', 8),
            'scope' => 'league',
            'type' => 'league_general',
            'status' => 'scheduled',
        ]);

        \App\Models\PoolUser::query()->create([
            'pool_id' => $pool->id,
            'user_id' => $joiner->id,
            'approved' => true,
        ]);

        Passport::actingAs($joiner, ['pool:join']);

        $response = $this->postJsonWithApiKey('/api/v1/pool/' . $pool->id . '/join', []);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'You are already in this pool.',
            ]);
    }

    public function test_join_creates_pool_user_fixtures_when_approval_is_not_required(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $joiner = User::factory()->create(['role' => 'user']);
        $league = $this->createLeague();
        $leagueSeason = $this->createLeagueSeason($league);
        $fixture = $this->createFixture($league, (int) $leagueSeason->year);
        $pool = Pool::query()->create([
            'owner_id' => $owner->id,
            'league_id' => $league->id,
            'league_season_id' => $leagueSeason->id,
            'name' => 'Pool',
            'description' => str_repeat('Descripcion valida. ', 8),
            'scope' => 'match',
            'type' => 'selected_score',
            'status' => 'scheduled',
            'accepts_join_requests' => true,
            'requires_join_approval' => false,
        ]);

        \App\Models\PoolFixture::query()->create([
            'pool_id' => $pool->id,
            'fixture_id' => $fixture->id,
            'score_selection_type' => 'selected_score',
        ]);

        Passport::actingAs($joiner, ['pool:join']);

        $response = $this->postJsonWithApiKey('/api/v1/pool/' . $pool->id . '/join', []);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'You have joined the pool successfully.',
            ]);

        $this->assertDatabaseHas('pool_users', [
            'pool_id' => $pool->id,
            'user_id' => $joiner->id,
            'approved' => true,
        ]);

        $this->assertDatabaseHas('pool_user_fixtures', [
            'pool_id' => $pool->id,
            'user_id' => $joiner->id,
            'fixture_id' => $fixture->id,
            'is_locked' => false,
        ]);
    }

    public function test_review_join_request_approves_pending_user_and_creates_pool_user_fixture(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $requestUser = User::factory()->create(['role' => 'user']);
        $league = $this->createLeague();
        $leagueSeason = $this->createLeagueSeason($league);
        $fixture = $this->createFixture($league, (int) $leagueSeason->year);
        $pool = Pool::query()->create([
            'owner_id' => $owner->id,
            'league_id' => $league->id,
            'league_season_id' => $leagueSeason->id,
            'name' => 'Pool',
            'description' => str_repeat('Descripcion valida. ', 8),
            'scope' => 'match',
            'type' => 'selected_score',
            'status' => 'scheduled',
        ]);

        \App\Models\PoolFixture::query()->create([
            'pool_id' => $pool->id,
            'fixture_id' => $fixture->id,
            'score_selection_type' => 'selected_score',
        ]);

        \App\Models\PoolUser::query()->create([
            'pool_id' => $pool->id,
            'user_id' => $requestUser->id,
            'approved' => false,
        ]);

        Passport::actingAs($owner, ['pool:add']);

        $response = $this->postJsonWithApiKey('/api/v1/pool/' . $pool->id . '/review-join-request', [
            'user_id' => $requestUser->id,
            'approved' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Join request approved successfully.',
            ]);

        $this->assertDatabaseHas('pool_users', [
            'pool_id' => $pool->id,
            'user_id' => $requestUser->id,
            'approved' => true,
        ]);

        $this->assertDatabaseHas('pool_user_fixtures', [
            'pool_id' => $pool->id,
            'user_id' => $requestUser->id,
            'fixture_id' => $fixture->id,
            'is_locked' => false,
        ]);
    }

    public function test_review_join_request_rejects_pending_user_and_deletes_pool_user(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $requestUser = User::factory()->create(['role' => 'user']);
        $pool = Pool::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Pool',
            'description' => str_repeat('Descripcion valida. ', 8),
            'scope' => 'league',
            'type' => 'league_general',
            'status' => 'scheduled',
        ]);

        \App\Models\PoolUser::query()->create([
            'pool_id' => $pool->id,
            'user_id' => $requestUser->id,
            'approved' => false,
        ]);

        Passport::actingAs($owner, ['pool:add']);

        $response = $this->postJsonWithApiKey('/api/v1/pool/' . $pool->id . '/review-join-request', [
            'user_id' => $requestUser->id,
            'approved' => false,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Join request rejected successfully.',
            ]);

        $this->assertDatabaseMissing('pool_users', [
            'pool_id' => $pool->id,
            'user_id' => $requestUser->id,
        ]);
    }

    public function test_index_returns_owned_and_joined_pools_with_summary_information(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $member = User::factory()->create(['role' => 'user']);
        $pending = User::factory()->create(['role' => 'user']);
        $otherOwner = User::factory()->create(['role' => 'user']);
        $league = $this->createLeague();
        $leagueSeason = $this->createLeagueSeason($league);
        $fixture = $this->createFixture($league, (int) $leagueSeason->year);
        $group = Group::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Pool Group',
            'language' => 'es',
        ]);

        $ownedPool = Pool::query()->create([
            'owner_id' => $owner->id,
            'league_id' => $league->id,
            'league_season_id' => $leagueSeason->id,
            'group_id' => $group->id,
            'name' => 'Owned Pool',
            'description' => str_repeat('Descripcion valida. ', 8),
            'scope' => 'match',
            'type' => 'selected_score',
            'status' => 'scheduled',
        ]);

        \App\Models\PoolFixture::query()->create([
            'pool_id' => $ownedPool->id,
            'fixture_id' => $fixture->id,
            'score_selection_type' => 'selected_score',
        ]);

        \App\Models\PoolUser::query()->create([
            'pool_id' => $ownedPool->id,
            'user_id' => $member->id,
            'approved' => true,
        ]);

        \App\Models\PoolUser::query()->create([
            'pool_id' => $ownedPool->id,
            'user_id' => $pending->id,
            'approved' => false,
        ]);

        $joinedPool = Pool::query()->create([
            'owner_id' => $otherOwner->id,
            'name' => 'Joined Pool',
            'description' => str_repeat('Descripcion valida. ', 8),
            'scope' => 'league',
            'type' => 'league_general',
            'status' => 'scheduled',
        ]);

        \App\Models\PoolUser::query()->create([
            'pool_id' => $joinedPool->id,
            'user_id' => $owner->id,
            'approved' => true,
        ]);

        Passport::actingAs($owner, ['pool:get']);

        $response = $this->getJsonWithApiKey('/api/v1/pool');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'pools')
            ->assertJsonFragment([
                'id' => $ownedPool->id,
                'owner_id' => $owner->id,
                'name' => 'Owned Pool',
                'is_owner' => true,
                'match_id' => $fixture->id,
                'total_approved_users' => 1,
                'total_pending_join_requests' => 1,
            ])
            ->assertJsonFragment([
                'id' => $joinedPool->id,
                'owner_id' => $otherOwner->id,
                'name' => 'Joined Pool',
                'is_owner' => false,
                'total_approved_users' => 1,
                'total_pending_join_requests' => null,
            ])
            ->assertJsonFragment([
                'id' => $group->id,
                'name' => 'Pool Group',
            ])
            ->assertJsonFragment([
                'id' => $league->id,
                'name' => 'League Test',
                'country' => null,
                'season' => 2026,
                'league_season_id' => $leagueSeason->id,
            ]);
    }

    public function test_show_returns_pool_detail_for_owner_with_pending_and_approved_users(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $friendUser = User::factory()->create([
            'role' => 'user',
            'name' => 'Alexandrea',
            'last_name' => 'Stokes',
            'avatar_path' => 'system/default01.png',
        ]);
        $pendingUser = User::factory()->create([
            'role' => 'user',
            'name' => 'Bridgette',
            'last_name' => 'Prohaska',
            'avatar_path' => 'system/default01.png',
        ]);
        $league = $this->createLeague();
        $leagueSeason = $this->createLeagueSeason($league);
        $fixture = $this->createFixture($league, (int) $leagueSeason->year);
        $group = Group::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Pool Group',
            'description' => 'Group description',
            'image_path' => 'group.png',
            'language' => 'es',
        ]);
        $group->users()->attach($owner->id, ['is_accepted' => true]);
        $group->users()->attach($friendUser->id, ['is_accepted' => true]);

        \App\Models\Friend::query()->create([
            'user_id' => $owner->id,
            'friend_id' => $friendUser->id,
        ]);
        \App\Models\Friend::query()->create([
            'user_id' => $friendUser->id,
            'friend_id' => $owner->id,
        ]);

        $pool = Pool::query()->create([
            'owner_id' => $owner->id,
            'league_id' => $league->id,
            'league_season_id' => $leagueSeason->id,
            'group_id' => $group->id,
            'name' => 'Owned Pool',
            'description' => str_repeat('Descripcion valida. ', 8),
            'scope' => 'match',
            'type' => 'selected_score',
            'status' => 'scheduled',
        ]);

        \App\Models\PoolFixture::query()->create([
            'pool_id' => $pool->id,
            'fixture_id' => $fixture->id,
            'score_selection_type' => 'selected_score',
            'possible_scores' => ['s00', 's01'],
        ]);

        \App\Models\PoolUser::query()->create([
            'pool_id' => $pool->id,
            'user_id' => $friendUser->id,
            'approved' => true,
        ]);
        \App\Models\PoolUser::query()->create([
            'pool_id' => $pool->id,
            'user_id' => $owner->id,
            'approved' => true,
        ]);
        \App\Models\PoolUser::query()->create([
            'pool_id' => $pool->id,
            'user_id' => $pendingUser->id,
            'approved' => false,
        ]);

        \App\Models\PoolUserFixture::query()->create([
            'pool_id' => $pool->id,
            'user_id' => $friendUser->id,
            'fixture_id' => $fixture->id,
            'league_id' => $league->id,
            'season' => 2026,
            'round' => 'Regular Season - 1',
            'timezone' => 'UTC',
            'fixture_date' => now()->addDay(),
            'timestamp' => now()->addDay()->timestamp,
            'status_long' => 'Not Started',
            'status_short' => 'NS',
            'home_team_id' => $fixture->home_team_id,
            'away_team_id' => $fixture->away_team_id,
            'home_goals' => 1,
            'away_goals' => 0,
            'entry_order' => 1,
            'is_locked' => false,
        ]);

        Passport::actingAs($owner, ['pool:get']);

        $response = $this->getJsonWithApiKey('/api/v1/pool/' . $pool->id);

        $response->assertStatus(200)
            ->assertJsonPath('pool.pool.id', $pool->id)
            ->assertJsonPath('pool.pool.is_owner', true)
            ->assertJsonPath('pool.pool.total_approved_users', 2)
            ->assertJsonPath('pool.pool.total_pending_join_requests', 1)
            ->assertJsonPath('pool.group.id', $group->id)
            ->assertJsonPath('pool.group.image_path', 'group.png')
            ->assertJsonPath('pool.group.name', 'Pool Group')
            ->assertJsonPath('pool.group.description', 'Group description')
            ->assertJsonPath('pool.group.total_users', 2)
            ->assertJsonPath('pool.league.league_id', $league->id)
            ->assertJsonPath('pool.league.name', 'League Test')
            ->assertJsonPath('pool.league.season', 2026)
            ->assertJsonPath('pool.league.league_season_id', $leagueSeason->id)
            ->assertJsonPath('pool.match.id', $fixture->id)
            ->assertJsonCount(2, 'pool.approved_users')
            ->assertJsonCount(1, 'pool.pending_users');

        $response->assertJsonFragment([
            'id' => $friendUser->id,
            'name' => 'Alexandrea',
            'last_name' => 'Stokes',
            'status' => 'friend',
        ]);

        $response->assertJsonFragment([
            'fixture_id' => $fixture->id,
            'home_goals' => 1,
            'away_goals' => 0,
            'entry_order' => 1,
        ]);
    }

    private function createLeague(): League
    {
        return League::query()->create([
            'provider' => 'api_football',
            'provider_league_id' => fake()->unique()->numberBetween(1000, 9999),
            'name' => 'League Test',
            'type' => 'league',
            'current' => true,
            'is_active' => true,
        ]);
    }

    private function createLeagueSeason(League $league): LeagueSeason
    {
        return LeagueSeason::query()->create([
            'league_id' => $league->id,
            'year' => 2026,
            'current' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createFixture(League $league, int $season, array $overrides = []): Fixture
    {
        $homeTeam = Team::query()->create([
            'provider' => 'api_football',
            'provider_team_id' => fake()->unique()->numberBetween(10000, 19999),
            'league_id' => $league->id,
            'season' => $season,
            'name' => 'Home Team',
            'is_active' => true,
        ]);

        $awayTeam = Team::query()->create([
            'provider' => 'api_football',
            'provider_team_id' => fake()->unique()->numberBetween(20000, 29999),
            'league_id' => $league->id,
            'season' => $season,
            'name' => 'Away Team',
            'is_active' => true,
        ]);

        return Fixture::query()->create(array_merge([
            'provider' => 'api_football',
            'provider_fixture_id' => fake()->unique()->numberBetween(30000, 39999),
            'league_id' => $league->id,
            'season' => $season,
            'round' => 'Regular Season - 1',
            'timezone' => 'UTC',
            'fixture_date' => now()->addDay(),
            'timestamp' => now()->addDay()->timestamp,
            'status_long' => 'Not Started',
            'status_short' => 'NS',
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'is_active' => true,
        ], $overrides));
    }
}
