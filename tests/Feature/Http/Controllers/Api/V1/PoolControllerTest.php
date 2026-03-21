<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Fixture;
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
            ->assertJsonPath('pool.possible_score_ids', null)
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
        $league = $this->createLeague();
        $leagueSeason = $this->createLeagueSeason($league);

        Passport::actingAs($user, ['pool:add']);

        $response = $this->postJsonWithApiKey('/api/v1/pool', [
            'league_id' => $league->id,
            'league_season_id' => $leagueSeason->id,
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
            ->assertJsonPath('pool.is_active', false);

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

    private function createFixture(League $league, int $season): Fixture
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

        return Fixture::query()->create([
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
        ]);
    }
}
