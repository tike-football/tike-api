<?php

namespace Tests\Unit\Services;

use App\Models\League;
use App\Services\FootballDataService\FootballDataClient;
use App\Services\FootballDataService\FootballDataFixture;
use App\Services\FootballDataService\FootballDataLeague;
use App\Services\FootballDataService\FootballDataPlayer;
use App\Services\FootballDataService\FootballDataStandings;
use App\Services\FootballDataService\FootballDataTeam;
use App\Services\FootballSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use RuntimeException;
use Tests\TestCase;

class FootballSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_league_creates_league_record(): void
    {
        $service = new FootballSyncService(new UnitFakeFootballDataClient(
            new FootballDataLeague(
                provider: 'api_football',
                endpoint: 'leagues',
                leagueId: 39,
                season: 2026,
                response: [
                    'league' => [
                        'id' => 39,
                        'name' => 'Premier League',
                        'type' => 'League',
                        'logo' => 'https://logo.example/premier.png',
                        'current' => true,
                    ],
                    'country' => [
                        'name' => 'England',
                        'code' => 'GB',
                        'flag' => 'https://flag.example/gb.png',
                    ],
                ],
                errorMessage: null,
            )
        ));

        $league = $service->syncLeague(39, 2026);

        $this->assertSame('api_football', $league->provider);
        $this->assertSame(39, $league->provider_league_id);
        $this->assertSame('Premier League', $league->name);
        $this->assertSame('league', $league->type);
        $this->assertNull($league->is_active);
        $this->assertNotNull($league->last_synced_at);

        $this->assertDatabaseHas('leagues', [
            'provider' => 'api_football',
            'provider_league_id' => 39,
            'name' => 'Premier League',
            'country_name' => 'England',
            'country_code' => 'GB',
            'is_active' => 0,
        ]);
    }

    public function test_sync_league_updates_existing_record(): void
    {
        League::create([
            'provider' => 'api_football',
            'provider_league_id' => 39,
            'name' => 'Old Name',
            'type' => 'league',
            'is_active' => false,
        ]);

        $service = new FootballSyncService(new UnitFakeFootballDataClient(
            new FootballDataLeague(
                provider: 'api_football',
                endpoint: 'leagues',
                leagueId: 39,
                season: 2026,
                response: [
                    'league' => [
                        'id' => 39,
                        'name' => 'Premier League Updated',
                        'type' => 'League',
                        'current' => true,
                    ],
                    'country' => [
                        'name' => 'England',
                        'code' => 'GB',
                    ],
                ],
                errorMessage: null,
            )
        ));

        $league = $service->syncLeague(39, 2026);

        $this->assertSame('Premier League Updated', $league->name);
        $this->assertFalse((bool) $league->is_active);
        $this->assertSame(1, League::count());
    }

    public function test_sync_league_throws_exception_when_provider_returns_error(): void
    {
        $service = new FootballSyncService(new UnitFakeFootballDataClient(
            new FootballDataLeague(
                provider: 'api_football',
                endpoint: 'leagues',
                leagueId: 39,
                season: 2026,
                response: null,
                errorMessage: 'Provider unavailable',
            )
        ));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Provider unavailable');

        $service->syncLeague(39, 2026);
    }

    public function test_sync_league_throws_exception_when_provider_returns_no_data_without_error_message(): void
    {
        $service = new FootballSyncService(new UnitFakeFootballDataClient(
            new FootballDataLeague(
                provider: 'api_football',
                endpoint: 'leagues',
                leagueId: 39,
                season: 2026,
                response: null,
                errorMessage: null,
            )
        ));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Provider returned no data.');

        $service->syncLeague(39, 2026);
    }
}

class UnitFakeFootballDataClient implements FootballDataClient
{
    public function __construct(private readonly FootballDataLeague $leagueResponse)
    {
    }

    public function getLeague(int $id, int $season): FootballDataLeague
    {
        return $this->leagueResponse;
    }

    public function getTeams(int $leagueId, int $season): Collection
    {
        return collect([
            new FootballDataTeam('api_football', 'teams', null, $leagueId, $season, null, 'Not implemented in fake'),
        ]);
    }

    public function getStandings(int $leagueId, int $season): FootballDataStandings
    {
        return new FootballDataStandings('api_football', 'standings', $leagueId, $season, null, 'Not implemented in fake');
    }

    public function getFixtures(int $leagueId, int $season): Collection
    {
        return collect([
            new FootballDataFixture('api_football', 'fixtures', null, $leagueId, $season, null, 'Not implemented in fake'),
        ]);
    }

    public function getPlayers(int $teamId, int $season): Collection
    {
        return collect([
            new FootballDataPlayer('api_football', 'players', null, $teamId, $season, null, 'Not implemented in fake'),
        ]);
    }
}
