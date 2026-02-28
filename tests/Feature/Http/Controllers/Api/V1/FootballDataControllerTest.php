<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\League;
use App\Models\LeagueSeason;
use App\Models\User;
use App\Services\FootballFixturesCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\WithApiKey;

class FootballDataControllerTest extends TestCase
{
    use RefreshDatabase, WithApiKey;

    public function test_get_fixtures_requires_api_key(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['football-data:get']);

        $response = $this->getJson('/api/v1/football-data/get-fixtures');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'API key is required.',
            ]);
    }

    public function test_get_fixtures_requires_bearer_token(): void
    {
        $response = $this->getJsonWithApiKey('/api/v1/football-data/get-fixtures');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_get_fixtures_requires_scope(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['different:scope']);

        $response = $this->getJsonWithApiKey('/api/v1/football-data/get-fixtures');

        $response->assertStatus(403);
    }

    public function test_get_fixtures_returns_full_cache_when_cache_fixtures_id_is_missing(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['football-data:get']);

        Cache::forever(FootballFixturesCacheService::CACHE_FIXTURES_ID, '20260221120000001');
        Cache::forever(FootballFixturesCacheService::CACHE_FIXTURES_CHANGES_ID, '20260221120000002');
        Cache::forever(FootballFixturesCacheService::CACHE_FIXTURES, [
            'meta' => ['source' => 'api_football'],
            'matches' => ['1001' => ['id' => 1001]],
        ]);

        $response = $this->getJsonWithApiKey('/api/v1/football-data/get-fixtures');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Fixtures cache loaded.',
                'cache_fixtures_id' => '20260221120000001',
                'cache_fixtures_changes_id' => '20260221120000002',
                'fixtures' => [
                    'meta' => ['source' => 'api_football'],
                ],
            ])
            ->assertJsonPath('fixtures.matches.1001.id', 1001);
    }

    public function test_get_fixtures_returns_full_cache_when_cache_fixtures_id_is_invalid(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['football-data:get']);

        Cache::forever(FootballFixturesCacheService::CACHE_FIXTURES_ID, '20260221120000001');
        Cache::forever(FootballFixturesCacheService::CACHE_FIXTURES_CHANGES_ID, '20260221120000002');
        Cache::forever(FootballFixturesCacheService::CACHE_FIXTURES, [
            'matches' => ['1001' => ['id' => 1001]],
        ]);

        $response = $this->getJsonWithApiKey('/api/v1/football-data/get-fixtures?cache_fixtures_id=invalid-id');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Fixtures cache loaded.',
            ])
            ->assertJsonPath('fixtures.matches.1001.id', 1001);
    }

    public function test_get_fixtures_returns_empty_object_when_cache_fixtures_id_matches_changes_id(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['football-data:get']);

        Cache::forever(FootballFixturesCacheService::CACHE_FIXTURES_ID, '20260221120000001');
        Cache::forever(FootballFixturesCacheService::CACHE_FIXTURES_CHANGES_ID, '20260221120000005');

        $response = $this->getJsonWithApiKey('/api/v1/football-data/get-fixtures?cache_fixtures_id=20260221120000005');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Fixtures cache is already up to date.',
                'fixtures' => [],
            ]);
    }

    public function test_get_fixtures_returns_full_cache_when_full_cache_id_is_greater_than_request_id(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['football-data:get']);

        Cache::forever(FootballFixturesCacheService::CACHE_FIXTURES_ID, '20260221120000005');
        Cache::forever(FootballFixturesCacheService::CACHE_FIXTURES_CHANGES_ID, '20260221120000005');
        Cache::forever(FootballFixturesCacheService::CACHE_FIXTURES, [
            'matches' => ['1001' => ['id' => 1001]],
        ]);

        $response = $this->getJsonWithApiKey('/api/v1/football-data/get-fixtures?cache_fixtures_id=20260221120000002');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Fixtures cache loaded.',
            ])
            ->assertJsonPath('fixtures.matches.1001.id', 1001);
    }

    public function test_get_fixtures_returns_changes_cache_when_full_cache_id_equals_request_and_changes_is_greater(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['football-data:get']);

        Cache::forever(FootballFixturesCacheService::CACHE_FIXTURES_ID, '20260221120000005');
        Cache::forever(FootballFixturesCacheService::CACHE_FIXTURES_CHANGES_ID, '20260221120000009');
        Cache::forever(FootballFixturesCacheService::CACHE_FIXTURES_CHANGES, [
            'matches' => ['1001' => ['id' => 1001, 'status' => 'live']],
        ]);

        $response = $this->getJsonWithApiKey('/api/v1/football-data/get-fixtures?cache_fixtures_id=20260221120000005');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Fixtures changes cache loaded.',
            ])
            ->assertJsonPath('fixtures.matches.1001.status', 'live');
    }

    public function test_get_league_structure_requires_scope(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['different:scope']);

        $response = $this->getJsonWithApiKey('/api/v1/football-data/get-league-structure?league_id=1&season=2026');

        $response->assertStatus(403);
    }

    public function test_get_league_structure_validates_required_fields(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['football-data:get']);

        $response = $this->getJsonWithApiKey('/api/v1/football-data/get-league-structure');

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Validation failed.',
            ])
            ->assertJsonValidationErrors(['league_id', 'season']);
    }

    public function test_get_league_structure_returns_404_when_league_season_not_found(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['football-data:get']);

        $response = $this->getJsonWithApiKey('/api/v1/football-data/get-league-structure?league_id=9999&season=2026');

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'League season not found.',
            ]);
    }

    public function test_get_league_structure_returns_structure_payload(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        Passport::actingAs($user, ['football-data:get']);

        $league = League::query()->create([
            'provider' => 'api_football',
            'provider_league_id' => 239,
            'name' => 'Liga BetPlay',
            'type' => 'cup',
            'country_name' => 'Colombia',
            'country_code' => 'CO',
            'logo' => null,
            'flag' => null,
            'current' => true,
            'is_active' => true,
            'external_payload' => null,
            'last_synced_at' => null,
        ]);

        LeagueSeason::query()->create([
            'league_id' => $league->id,
            'year' => 2026,
            'start' => '2026-01-01',
            'end' => '2026-12-31',
            'current' => true,
            'structure' => [
                'league_id' => $league->id,
                'season_structure' => [
                    'current_phase' => 'F1',
                ],
            ],
        ]);

        $response = $this->getJsonWithApiKey('/api/v1/football-data/get-league-structure?league_id=' . $league->id . '&season=2026');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'League structure loaded.',
                'league_id' => $league->id,
                'season' => 2026,
            ])
            ->assertJsonPath('structure.season_structure.current_phase', 'F1');
    }
}
