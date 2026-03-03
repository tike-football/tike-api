<?php

namespace Tests\Unit\Services;

use App\Models\Fixture;
use App\Models\FixtureTeamStat;
use App\Models\League;
use App\Models\LeagueStanding;
use App\Models\LeagueStandingRow;
use App\Models\Player;
use App\Models\PlayerLeagueStat;
use App\Models\Team;
use App\Services\FootballFixturesCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FootballFixturesCacheServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_cache_fixtures_builds_payload_and_stores_it_in_cache(): void
    {
        $league = League::create([
            'provider' => 'api_football',
            'provider_league_id' => 39,
            'name' => 'Premier League',
            'country_name' => 'England',
            'type' => 'league',
            'current' => true,
        ]);

        $homeTeam = Team::create([
            'provider' => 'api_football',
            'provider_team_id' => 42,
            'name' => 'Arsenal',
            'code' => 'ARS',
            'country_name' => 'England',
            'venue_provider_id' => 494,
            'venue_name' => 'Emirates Stadium',
            'venue_city' => 'London',
        ]);

        $awayTeam = Team::create([
            'provider' => 'api_football',
            'provider_team_id' => 49,
            'name' => 'Chelsea',
            'code' => 'CHE',
            'country_name' => 'England',
            'venue_provider_id' => 519,
            'venue_name' => 'Stamford Bridge',
            'venue_city' => 'London',
        ]);

        $fixture = Fixture::create([
            'provider' => 'api_football',
            'provider_fixture_id' => 1001,
            'league_id' => $league->id,
            'season' => 2026,
            'round' => 'Regular Season - 10',
            'status_short' => '2H',
            'status_elapsed' => 67,
            'fixture_date' => now(),
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'home_goals' => 2,
            'away_goals' => 1,
        ]);

        FixtureTeamStat::create([
            'provider' => 'api_football',
            'fixture_id' => $fixture->id,
            'team_id' => $homeTeam->id,
            'is_home' => true,
            'goals' => 2,
            'winner' => true,
            'raw_statistics' => ['possession' => 54],
        ]);

        FixtureTeamStat::create([
            'provider' => 'api_football',
            'fixture_id' => $fixture->id,
            'team_id' => $awayTeam->id,
            'is_home' => false,
            'goals' => 1,
            'winner' => false,
            'raw_statistics' => ['possession' => 46],
        ]);

        $standing = LeagueStanding::create([
            'provider' => 'api_football',
            'league_id' => $league->id,
            'season' => 2026,
            'standing_group' => 'Premier League',
        ]);

        LeagueStandingRow::create([
            'standing_id' => $standing->id,
            'team_id' => $homeTeam->id,
            'rank_position' => 3,
            'points' => 55,
            'matches_played' => 26,
            'matches_win' => 16,
            'matches_draw' => 7,
            'matches_lose' => 3,
            'goals_for' => 49,
            'goals_against' => 24,
            'goals_diff' => 25,
            'raw_row_payload' => ['rank' => 3],
        ]);

        $player = Player::create([
            'provider' => 'api_football',
            'provider_player_id' => 501,
            'full_name' => 'Player A',
        ]);

        PlayerLeagueStat::create([
            'provider' => 'api_football',
            'player_id' => $player->id,
            'team_id' => $homeTeam->id,
            'league_id' => $league->id,
            'season' => 2026,
            'games_appearences' => 21,
            'goals_total' => 9,
            'goals_assists' => 5,
            'cards_yellow' => 3,
            'cards_red' => 0,
            'raw_statistics' => ['any' => 'value'],
        ]);

        Cache::forget(FootballFixturesCacheService::CACHE_FIXTURES);
        Cache::forget(FootballFixturesCacheService::CACHE_FIXTURES_ID);
        Cache::forget(FootballFixturesCacheService::CACHE_FIXTURES_CHANGES_ID);

        $service = app(FootballFixturesCacheService::class);
        $payload = $service->cacheFixtures();
        $cached = Cache::get(FootballFixturesCacheService::CACHE_FIXTURES);
        $cacheId = Cache::get(FootballFixturesCacheService::CACHE_FIXTURES_ID);
        $changesCacheId = Cache::get(FootballFixturesCacheService::CACHE_FIXTURES_CHANGES_ID);

        $this->assertNotNull($cached);
        $this->assertSame($payload, $cached);

        $this->assertArrayHasKey('meta', $cached);
        $this->assertArrayHasKey('indexes', $cached);
        $this->assertArrayHasKey('leagues', $cached);
        $this->assertArrayHasKey('teams', $cached);
        $this->assertArrayHasKey('matches', $cached);
        $this->assertArrayHasKey('players', $cached);

        $leagueKey = (string) $league->id;
        $fixtureKey = (string) $fixture->id;
        $teamKey = (string) $homeTeam->id;
        $playerKey = (string) $player->id;

        $this->assertArrayHasKey($leagueKey, $cached['leagues']);
        $this->assertArrayHasKey($fixtureKey, $cached['matches']);
        $this->assertArrayHasKey($teamKey, $cached['teams']);
        $this->assertArrayHasKey($playerKey, $cached['players']);

        $this->assertContains($fixtureKey, $cached['indexes']['by_status']['live']);
        $this->assertContains($fixtureKey, $cached['indexes']['by_league'][$leagueKey]['live']);
        $this->assertContains($fixtureKey, $cached['indexes']['team_matches'][$teamKey]['live']);
        $this->assertSame('Regular Season - 10', $cached['matches'][$fixtureKey]['round']);
        $this->assertNotNull($cacheId);
        $this->assertMatchesRegularExpression('/^\d{17}$/', (string) $cacheId);
        $this->assertSame($cacheId, $changesCacheId);
    }

    public function test_cache_fixture_changes_stores_changes_and_does_not_update_full_cache_id(): void
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

        Cache::forget(FootballFixturesCacheService::CACHE_FIXTURES);
        Cache::forget(FootballFixturesCacheService::CACHE_FIXTURES_CHANGES);
        Cache::forget(FootballFixturesCacheService::CACHE_FIXTURES_ID);
        Cache::forget(FootballFixturesCacheService::CACHE_FIXTURES_CHANGES_ID);

        $service = app(FootballFixturesCacheService::class);
        $service->cacheFixtures();
        $fullCacheId = Cache::get(FootballFixturesCacheService::CACHE_FIXTURES_ID);
        $initialChangesCacheId = Cache::get(FootballFixturesCacheService::CACHE_FIXTURES_CHANGES_ID);

        $fixture->update([
            'home_goals' => 1,
            'status_elapsed' => 15,
        ]);

        $changes = $service->cacheFixtureChanges();

        $fixtureKey = (string) $fixture->id;

        $this->assertArrayHasKey($fixtureKey, $changes['matches']);
        $this->assertSame(1, $changes['matches'][$fixtureKey]['score']['home']);
        $this->assertSame($fullCacheId, Cache::get(FootballFixturesCacheService::CACHE_FIXTURES_ID));
        $updatedChangesCacheId = Cache::get(FootballFixturesCacheService::CACHE_FIXTURES_CHANGES_ID);
        $this->assertNotNull($updatedChangesCacheId);
        $this->assertNotSame($initialChangesCacheId, $updatedChangesCacheId);
        $this->assertNotNull(Cache::get(FootballFixturesCacheService::CACHE_FIXTURES_CHANGES));

        $secondRun = $service->cacheFixtureChanges();
        $this->assertArrayHasKey($fixtureKey, $secondRun['matches']);
        $this->assertSame(1, $secondRun['matches'][$fixtureKey]['score']['home']);
    }
}
