<?php

namespace Tests\Feature\Console;

use App\Models\Fixture;
use App\Models\League;
use App\Models\Team;
use App\Services\FootballFixturesCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheFixturesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_cache_fixtures_command_stores_payload_in_cache(): void
    {
        $league = League::create([
            'provider' => 'api_football',
            'provider_league_id' => 39,
            'name' => 'Premier League',
            'type' => 'league',
            'current' => true,
        ]);

        $homeTeam = Team::create([
            'provider' => 'api_football',
            'provider_team_id' => 42,
            'name' => 'Arsenal',
        ]);

        $awayTeam = Team::create([
            'provider' => 'api_football',
            'provider_team_id' => 49,
            'name' => 'Chelsea',
        ]);

        $fixture = Fixture::create([
            'provider' => 'api_football',
            'provider_fixture_id' => 1001,
            'league_id' => $league->id,
            'season' => 2026,
            'status_short' => 'NS',
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
        ]);

        Cache::forget(FootballFixturesCacheService::CACHE_FIXTURES);

        $this->artisan('football-data:cache-fixtures')
            ->assertExitCode(0);

        $cached = Cache::get(FootballFixturesCacheService::CACHE_FIXTURES);

        $this->assertNotNull($cached);
        $this->assertArrayHasKey('matches', $cached);
        $this->assertArrayHasKey((string) $fixture->id, $cached['matches']);
    }

    public function test_cache_fixtures_command_rebuilds_when_full_and_changes_ids_are_equal(): void
    {
        $league = League::create([
            'provider' => 'api_football',
            'provider_league_id' => 39,
            'name' => 'Premier League',
            'type' => 'league',
            'current' => true,
        ]);

        $homeTeam = Team::create([
            'provider' => 'api_football',
            'provider_team_id' => 42,
            'name' => 'Arsenal',
        ]);

        $awayTeam = Team::create([
            'provider' => 'api_football',
            'provider_team_id' => 49,
            'name' => 'Chelsea',
        ]);

        $fixture = Fixture::create([
            'provider' => 'api_football',
            'provider_fixture_id' => 1001,
            'league_id' => $league->id,
            'season' => 2026,
            'status_short' => 'NS',
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
        ]);

        Cache::forget(FootballFixturesCacheService::CACHE_FIXTURES);
        Cache::forever(FootballFixturesCacheService::CACHE_FIXTURES_ID, '20260221153000000');
        Cache::forever(FootballFixturesCacheService::CACHE_FIXTURES_CHANGES_ID, '20260221153000000');

        $this->artisan('football-data:cache-fixtures')
            ->assertExitCode(0);

        $cached = Cache::get(FootballFixturesCacheService::CACHE_FIXTURES);

        $this->assertNotNull($cached);
        $this->assertArrayHasKey('matches', $cached);
        $this->assertArrayHasKey((string) $fixture->id, $cached['matches']);
    }

    public function test_cache_fixtures_changes_command_stores_changes_payload_in_cache(): void
    {
        $league = League::create([
            'provider' => 'api_football',
            'provider_league_id' => 39,
            'name' => 'Premier League',
            'type' => 'league',
            'current' => true,
        ]);

        $homeTeam = Team::create([
            'provider' => 'api_football',
            'provider_team_id' => 42,
            'name' => 'Arsenal',
        ]);

        $awayTeam = Team::create([
            'provider' => 'api_football',
            'provider_team_id' => 49,
            'name' => 'Chelsea',
        ]);

        $fixture = Fixture::create([
            'provider' => 'api_football',
            'provider_fixture_id' => 1001,
            'league_id' => $league->id,
            'season' => 2026,
            'status_short' => '1H',
            'status_elapsed' => 10,
            'fixture_date' => now(),
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'home_goals' => 0,
            'away_goals' => 0,
        ]);

        app(\App\Services\FootballFixturesCacheService::class)->cacheFixtureChanges();
        app(\App\Services\FootballFixturesCacheService::class)->cacheFixtures();

        $fixture->update([
            'home_goals' => 1,
            'status_elapsed' => 15,
        ]);

        Cache::forever(FootballFixturesCacheService::CACHE_FIXTURES_CHANGES, [
            'matches' => ['stale' => true],
        ]);

        $this->artisan('football-data:cache-fixtures-changes')
            ->assertExitCode(0);

        $cached = Cache::get(FootballFixturesCacheService::CACHE_FIXTURES_CHANGES);

        $this->assertNotNull($cached);
        $this->assertArrayHasKey('matches', $cached);
        $fixtureKey = (string) $fixture->id;
        $this->assertArrayHasKey($fixtureKey, $cached['matches']);
        $this->assertSame(1, $cached['matches'][$fixtureKey]['score']['home']);
    }

    public function test_cache_fixtures_changes_command_skips_when_no_relevant_fixtures(): void
    {
        $league = League::create([
            'provider' => 'api_football',
            'provider_league_id' => 39,
            'name' => 'Premier League',
            'type' => 'league',
            'current' => true,
        ]);

        $homeTeam = Team::create([
            'provider' => 'api_football',
            'provider_team_id' => 42,
            'name' => 'Arsenal',
        ]);

        $awayTeam = Team::create([
            'provider' => 'api_football',
            'provider_team_id' => 49,
            'name' => 'Chelsea',
        ]);

        Fixture::create([
            'provider' => 'api_football',
            'provider_fixture_id' => 1001,
            'league_id' => $league->id,
            'season' => 2026,
            'status_short' => 'NS',
            'fixture_date' => now()->addHours(2),
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
        ]);

        Cache::forget(FootballFixturesCacheService::CACHE_FIXTURES_CHANGES);

        $this->artisan('football-data:cache-fixtures-changes')
            ->assertExitCode(0);

        $this->assertNull(Cache::get(FootballFixturesCacheService::CACHE_FIXTURES_CHANGES));
    }
}
