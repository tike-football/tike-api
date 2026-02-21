<?php

namespace Tests\Unit\Services;

use App\Events\FootballData\LeagueSynced;
use App\Events\FootballData\TeamSynced;
use App\Models\League;
use App\Models\Fixture;
use App\Models\FixtureTeamStat;
use App\Models\Team;
use App\Models\Player;
use App\Models\PlayerLeagueStat;
use App\Models\TeamPlayerSeason;
use App\Services\FootballDataService\FootballDataClient;
use App\Services\FootballDataService\FootballDataFixture;
use App\Services\FootballDataService\FootballDataLeague;
use App\Services\FootballDataService\FootballDataPlayer;
use App\Services\FootballDataService\FootballDataStandings;
use App\Services\FootballDataService\FootballDataTeam;
use App\Services\FootballSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class FootballSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_league_creates_league_record(): void
    {
        Event::fake([LeagueSynced::class]);

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

        Event::assertDispatched(LeagueSynced::class, function (LeagueSynced $event) use ($league): bool {
            return $event->league->is($league);
        });
    }

    public function test_sync_league_updates_existing_record(): void
    {
        Event::fake([LeagueSynced::class]);

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

        Event::assertDispatched(LeagueSynced::class, function (LeagueSynced $event) use ($league): bool {
            return $event->league->is($league);
        });
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

    public function test_sync_teams_saves_all_teams_for_a_league(): void
    {
        Event::fake([TeamSynced::class]);

        $league = League::create([
            'provider' => 'api_football',
            'provider_league_id' => 39,
            'name' => 'Premier League',
            'type' => 'league',
        ]);

        $teamsResponse = collect([
            new FootballDataTeam(
                provider: 'api_football',
                endpoint: 'teams',
                teamId: 33,
                leagueId: 39,
                season: 2026,
                response: [
                    'team' => [
                        'id' => 33,
                        'name' => 'Manchester United',
                        'code' => 'MUN',
                        'country' => 'England',
                        'founded' => 1878,
                        'national' => false,
                        'logo' => 'https://logo.example/manchester-united.png',
                    ],
                    'venue' => [
                        'id' => 556,
                        'name' => 'Old Trafford',
                        'city' => 'Manchester',
                        'capacity' => 74140,
                    ],
                ],
                errorMessage: null,
            ),
            new FootballDataTeam(
                provider: 'api_football',
                endpoint: 'teams',
                teamId: 50,
                leagueId: 39,
                season: 2026,
                response: [
                    'team' => [
                        'id' => 50,
                        'name' => 'Manchester City',
                        'code' => 'MCI',
                        'country' => 'England',
                        'founded' => 1880,
                        'national' => false,
                        'logo' => 'https://logo.example/manchester-city.png',
                    ],
                    'venue' => [
                        'id' => 555,
                        'name' => 'Etihad Stadium',
                        'city' => 'Manchester',
                        'capacity' => 55097,
                    ],
                ],
                errorMessage: null,
            ),
        ]);

        $service = new FootballSyncService(new UnitFakeFootballDataClient(
            leagueResponse: new FootballDataLeague(
                provider: 'api_football',
                endpoint: 'leagues',
                leagueId: 39,
                season: 2026,
                response: ['league' => ['id' => 39, 'name' => 'Premier League', 'type' => 'League']],
                errorMessage: null,
            ),
            teamsResponse: $teamsResponse,
        ));

        $savedTeams = $service->syncTeams(39, 2026);

        $this->assertCount(2, $savedTeams);
        $this->assertSame(2, Team::count());
        $this->assertDatabaseHas('teams', [
            'provider' => 'api_football',
            'provider_team_id' => 33,
            'league_id' => $league->id,
            'season' => 2026,
            'name' => 'Manchester United',
        ]);
        $this->assertDatabaseHas('teams', [
            'provider' => 'api_football',
            'provider_team_id' => 50,
            'league_id' => $league->id,
            'season' => 2026,
            'name' => 'Manchester City',
        ]);

        Event::assertDispatched(TeamSynced::class, 2);
    }

    public function test_sync_teams_throws_exception_when_provider_returns_error(): void
    {
        $service = new FootballSyncService(new UnitFakeFootballDataClient(
            leagueResponse: new FootballDataLeague(
                provider: 'api_football',
                endpoint: 'leagues',
                leagueId: 39,
                season: 2026,
                response: ['league' => ['id' => 39, 'name' => 'Premier League', 'type' => 'League']],
                errorMessage: null,
            ),
            teamsResponse: collect([
                new FootballDataTeam(
                    provider: 'api_football',
                    endpoint: 'teams',
                    teamId: null,
                    leagueId: 39,
                    season: 2026,
                    response: null,
                    errorMessage: 'Provider unavailable',
                ),
            ]),
        ));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Provider unavailable');

        $service->syncTeams(39, 2026);
    }

    public function test_sync_fixtures_saves_all_fixtures_for_a_league(): void
    {
        $league = League::create([
            'provider' => 'api_football',
            'provider_league_id' => 39,
            'name' => 'Premier League',
            'type' => 'league',
        ]);

        $homeTeam = Team::create([
            'provider' => 'api_football',
            'provider_team_id' => 33,
            'name' => 'Manchester United',
        ]);

        $awayTeam = Team::create([
            'provider' => 'api_football',
            'provider_team_id' => 50,
            'name' => 'Manchester City',
        ]);

        $fixturesResponse = collect([
            new FootballDataFixture(
                provider: 'api_football',
                endpoint: 'fixtures',
                fixtureId: 1001,
                leagueId: 39,
                season: 2026,
                response: [
                    'fixture' => [
                        'id' => 1001,
                        'date' => '2026-08-12T16:30:00+00:00',
                        'timezone' => 'UTC',
                        'timestamp' => 1786552200,
                        'venue' => [
                            'id' => 556,
                            'name' => 'Old Trafford',
                            'city' => 'Manchester',
                        ],
                        'status' => [
                            'long' => 'Match Finished',
                            'short' => 'FT',
                            'elapsed' => 90,
                        ],
                    ],
                    'league' => [
                        'id' => 39,
                        'name' => 'Premier League',
                        'country' => 'England',
                        'season' => 2026,
                        'round' => 'Regular Season - 1',
                    ],
                    'teams' => [
                        'home' => ['id' => 33],
                        'away' => ['id' => 50],
                    ],
                    'goals' => [
                        'home' => 2,
                        'away' => 1,
                    ],
                ],
                errorMessage: null,
            ),
        ]);

        $service = new FootballSyncService(new UnitFakeFootballDataClient(
            leagueResponse: new FootballDataLeague(
                provider: 'api_football',
                endpoint: 'leagues',
                leagueId: 39,
                season: 2026,
                response: ['league' => ['id' => 39, 'name' => 'Premier League', 'type' => 'League']],
                errorMessage: null,
            ),
            fixturesResponse: $fixturesResponse,
        ));

        $savedFixtures = $service->syncFixtures(39, 2026);

        $this->assertCount(1, $savedFixtures);
        $this->assertSame(1, Fixture::count());
        $this->assertSame(2, FixtureTeamStat::count());
        $this->assertDatabaseHas('fixtures', [
            'provider' => 'api_football',
            'provider_fixture_id' => 1001,
            'league_id' => $league->id,
            'season' => 2026,
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'home_goals' => 2,
            'away_goals' => 1,
            'status_short' => 'FT',
            'is_active' => 0,
        ]);
        $fixture = Fixture::query()->where('provider_fixture_id', 1001)->firstOrFail();
        $this->assertDatabaseHas('fixture_team_stats', [
            'fixture_id' => $fixture->id,
            'team_id' => $homeTeam->id,
            'is_home' => 1,
            'goals' => 2,
        ]);
        $this->assertDatabaseHas('fixture_team_stats', [
            'fixture_id' => $fixture->id,
            'team_id' => $awayTeam->id,
            'is_home' => 0,
            'goals' => 1,
        ]);
    }

    public function test_sync_fixtures_throws_exception_when_provider_returns_error(): void
    {
        $service = new FootballSyncService(new UnitFakeFootballDataClient(
            leagueResponse: new FootballDataLeague(
                provider: 'api_football',
                endpoint: 'leagues',
                leagueId: 39,
                season: 2026,
                response: ['league' => ['id' => 39, 'name' => 'Premier League', 'type' => 'League']],
                errorMessage: null,
            ),
            fixturesResponse: collect([
                new FootballDataFixture(
                    provider: 'api_football',
                    endpoint: 'fixtures',
                    fixtureId: null,
                    leagueId: 39,
                    season: 2026,
                    response: null,
                    errorMessage: 'Provider unavailable',
                ),
            ]),
        ));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Provider unavailable');

        $service->syncFixtures(39, 2026);
    }

    public function test_sync_fixtures_skips_fixture_when_league_does_not_exist_locally(): void
    {
        Team::create([
            'provider' => 'api_football',
            'provider_team_id' => 33,
            'name' => 'Manchester United',
        ]);

        Team::create([
            'provider' => 'api_football',
            'provider_team_id' => 50,
            'name' => 'Manchester City',
        ]);

        $fixturesResponse = collect([
            new FootballDataFixture(
                provider: 'api_football',
                endpoint: 'fixtures',
                fixtureId: 1002,
                leagueId: 39,
                season: 2026,
                response: [
                    'fixture' => [
                        'id' => 1002,
                        'status' => ['short' => 'NS'],
                    ],
                    'league' => [
                        'id' => 39,
                        'season' => 2026,
                    ],
                    'teams' => [
                        'home' => ['id' => 33],
                        'away' => ['id' => 50],
                    ],
                    'goals' => [
                        'home' => null,
                        'away' => null,
                    ],
                ],
                errorMessage: null,
            ),
        ]);

        $service = new FootballSyncService(new UnitFakeFootballDataClient(
            leagueResponse: new FootballDataLeague(
                provider: 'api_football',
                endpoint: 'leagues',
                leagueId: 39,
                season: 2026,
                response: ['league' => ['id' => 39, 'name' => 'Premier League', 'type' => 'League']],
                errorMessage: null,
            ),
            fixturesResponse: $fixturesResponse,
        ));

        $savedFixtures = $service->syncFixtures(39, 2026);

        $this->assertCount(0, $savedFixtures);
        $this->assertSame(0, Fixture::count());
        $this->assertSame(0, FixtureTeamStat::count());
    }

    public function test_sync_players_saves_player_team_season_and_league_stats(): void
    {
        $league = League::create([
            'provider' => 'api_football',
            'provider_league_id' => 39,
            'name' => 'Premier League',
            'type' => 'league',
        ]);

        $team = Team::create([
            'provider' => 'api_football',
            'provider_team_id' => 33,
            'league_id' => $league->id,
            'season' => 2026,
            'name' => 'Manchester United',
        ]);

        $playersResponse = collect([
            new FootballDataPlayer(
                provider: 'api_football',
                endpoint: 'players',
                playerId: 276,
                teamId: 33,
                season: 2026,
                response: [
                    'player' => [
                        'id' => 276,
                        'name' => 'Bruno Fernandes',
                        'firstname' => 'Bruno',
                        'lastname' => 'Fernandes',
                        'age' => 31,
                        'birth' => [
                            'date' => '1994-09-08',
                            'place' => 'Maia',
                            'country' => 'Portugal',
                        ],
                        'nationality' => 'Portugal',
                        'height' => '179 cm',
                        'weight' => '69 kg',
                        'injured' => false,
                        'photo' => 'https://photo.example/bruno.png',
                    ],
                    'statistics' => [
                        [
                            'team' => [
                                'id' => 33,
                            ],
                            'league' => [
                                'id' => 39,
                                'name' => 'Premier League',
                                'country' => 'England',
                            ],
                            'games' => [
                                'appearences' => 33,
                                'lineups' => 31,
                                'minutes' => 2870,
                                'number' => 8,
                                'position' => 'Midfielder',
                            ],
                            'goals' => [
                                'total' => 11,
                                'assists' => 9,
                            ],
                            'cards' => [
                                'yellow' => 7,
                                'red' => 0,
                            ],
                        ],
                    ],
                ],
                errorMessage: null,
            ),
        ]);

        $service = new FootballSyncService(new UnitFakeFootballDataClient(
            leagueResponse: new FootballDataLeague(
                provider: 'api_football',
                endpoint: 'leagues',
                leagueId: 39,
                season: 2026,
                response: ['league' => ['id' => 39, 'name' => 'Premier League', 'type' => 'League']],
                errorMessage: null,
            ),
            teamsResponse: collect(),
            playersResponse: $playersResponse,
        ));

        $savedPlayers = $service->syncPlayers(33, 2026);

        $this->assertCount(1, $savedPlayers);
        $this->assertSame(1, Player::count());
        $this->assertSame(1, TeamPlayerSeason::count());
        $this->assertSame(1, PlayerLeagueStat::count());

        $this->assertDatabaseHas('players', [
            'provider' => 'api_football',
            'provider_player_id' => 276,
            'full_name' => 'Bruno Fernandes',
            'nationality' => 'Portugal',
        ]);

        $player = Player::query()
            ->where('provider', 'api_football')
            ->where('provider_player_id', 276)
            ->firstOrFail();

        $this->assertDatabaseHas('team_player_season', [
            'player_id' => $player->id,
            'team_id' => $team->id,
            'season' => 2026,
            'position' => 'Midfielder',
        ]);

        $this->assertDatabaseHas('player_league_stats', [
            'player_id' => $player->id,
            'team_id' => $team->id,
            'league_id' => $league->id,
            'season' => 2026,
            'games_appearences' => 33,
            'goals_total' => 11,
        ]);
    }

    public function test_sync_players_throws_exception_when_provider_returns_error(): void
    {
        Team::create([
            'provider' => 'api_football',
            'provider_team_id' => 33,
            'name' => 'Manchester United',
        ]);

        $service = new FootballSyncService(new UnitFakeFootballDataClient(
            leagueResponse: new FootballDataLeague(
                provider: 'api_football',
                endpoint: 'leagues',
                leagueId: 39,
                season: 2026,
                response: ['league' => ['id' => 39, 'name' => 'Premier League', 'type' => 'League']],
                errorMessage: null,
            ),
            playersResponse: collect([
                new FootballDataPlayer(
                    provider: 'api_football',
                    endpoint: 'players',
                    playerId: null,
                    teamId: 33,
                    season: 2026,
                    response: null,
                    errorMessage: 'Provider unavailable',
                ),
            ]),
        ));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Provider unavailable');

        $service->syncPlayers(33, 2026);
    }

    public function test_sync_players_stores_null_age_when_provider_returns_invalid_age(): void
    {
        $league = League::create([
            'provider' => 'api_football',
            'provider_league_id' => 244,
            'name' => 'Veikkausliiga',
            'type' => 'league',
        ]);

        Team::create([
            'provider' => 'api_football',
            'provider_team_id' => 1165,
            'league_id' => $league->id,
            'season' => 2025,
            'name' => 'KuPS',
        ]);

        $playersResponse = collect([
            new FootballDataPlayer(
                provider: 'api_football',
                endpoint: 'players',
                playerId: 505941,
                teamId: 1165,
                season: 2025,
                response: [
                    'player' => [
                        'id' => 505941,
                        'name' => 'R. Tahkola',
                        'firstname' => 'Roopert',
                        'lastname' => 'Tahkola',
                        'age' => 2025,
                        'birth' => [
                            'date' => null,
                            'place' => null,
                            'country' => 'Finland',
                        ],
                        'nationality' => 'Finland',
                        'injured' => false,
                    ],
                    'statistics' => [
                        [
                            'team' => ['id' => 1165],
                            'league' => ['id' => 244, 'name' => 'Veikkausliiga', 'country' => 'Finland'],
                            'games' => ['appearences' => 2, 'lineups' => 0, 'minutes' => 6, 'position' => 'Midfielder'],
                            'goals' => ['total' => 0, 'assists' => 0],
                            'cards' => ['yellow' => 0, 'red' => 0],
                        ],
                    ],
                ],
                errorMessage: null,
            ),
        ]);

        $service = new FootballSyncService(new UnitFakeFootballDataClient(
            leagueResponse: new FootballDataLeague(
                provider: 'api_football',
                endpoint: 'leagues',
                leagueId: 244,
                season: 2025,
                response: ['league' => ['id' => 244, 'name' => 'Veikkausliiga', 'type' => 'League']],
                errorMessage: null,
            ),
            playersResponse: $playersResponse,
        ));

        $service->syncPlayers(1165, 2025);

        $this->assertDatabaseHas('players', [
            'provider' => 'api_football',
            'provider_player_id' => 505941,
            'full_name' => 'R. Tahkola',
        ]);

        $player = Player::query()
            ->where('provider', 'api_football')
            ->where('provider_player_id', 505941)
            ->firstOrFail();

        $this->assertNull($player->age);
    }
}

class UnitFakeFootballDataClient implements FootballDataClient
{
    /**
     * @param Collection<int, FootballDataTeam>|null $teamsResponse
     */
    public function __construct(
        private readonly FootballDataLeague $leagueResponse,
        private readonly ?Collection $teamsResponse = null,
        private readonly ?Collection $fixturesResponse = null,
        private readonly ?Collection $playersResponse = null,
    )
    {
    }

    public function getLeague(int $id, int $season): FootballDataLeague
    {
        return $this->leagueResponse;
    }

    public function getTeams(int $leagueId, int $season): Collection
    {
        return $this->teamsResponse ?? collect([
            new FootballDataTeam('api_football', 'teams', null, $leagueId, $season, null, 'Not implemented in fake'),
        ]);
    }

    public function getStandings(int $leagueId, int $season): FootballDataStandings
    {
        return new FootballDataStandings('api_football', 'standings', $leagueId, $season, null, 'Not implemented in fake');
    }

    public function getFixtures(int $leagueId, int $season): Collection
    {
        return $this->fixturesResponse ?? collect([
            new FootballDataFixture('api_football', 'fixtures', null, $leagueId, $season, null, 'Not implemented in fake'),
        ]);
    }

    public function getPlayers(int $teamId, int $season): Collection
    {
        return $this->playersResponse ?? collect([
            new FootballDataPlayer('api_football', 'players', null, $teamId, $season, null, 'Not implemented in fake'),
        ]);
    }
}
