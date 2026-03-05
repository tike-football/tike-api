<?php

namespace App\Services;

use App\Events\FootballData\FixtureFinished;
use App\Events\FootballData\LeagueSynced;
use App\Events\FootballData\LeagueTeamsSynced;
use App\Events\FootballData\TeamSynced;
use App\Models\Fixture;
use App\Models\FixtureTeamStat;
use App\Models\League;
use App\Models\LeagueSeason;
use App\Models\LeagueStanding;
use App\Models\LeagueStandingRow;
use App\Models\Player;
use App\Models\PlayerLeagueStat;
use App\Models\Team;
use App\Models\TeamPlayerSeason;
use App\Services\FootballDataService\FootballDataClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FootballSyncService
{
    /**
     * @var array<int, string>
     */
    public const FINISHED_STATUS_SHORTS = ['FT', 'AET', 'PEN'];

    public function __construct(
        private readonly FootballDataClient $footballDataClient
    ) {
    }

    public function syncLeague(int $leagueId, int $season): League
    {
        $footballLeague = $this->footballDataClient->getLeague($leagueId, $season);

        if ($footballLeague->errorMessage !== null || $footballLeague->response === null) {
            throw new RuntimeException($footballLeague->errorMessage ?? 'Provider returned no data.');
        }

        $leagueData = $footballLeague->response['league'] ?? [];
        $countryData = $footballLeague->response['country'] ?? [];
        $providerLeagueId = (int) ($leagueData['id'] ?? $leagueId);
        $type = strtolower((string) ($leagueData['type'] ?? 'league'));

        $league = League::updateOrCreate(
            [
                'provider' => $footballLeague->provider,
                'provider_league_id' => $providerLeagueId,
            ],
            [
                'name' => (string) ($leagueData['name'] ?? ''),
                'type' => $type,
                'country_name' => isset($countryData['name']) ? (string) $countryData['name'] : null,
                'country_code' => isset($countryData['code']) ? (string) $countryData['code'] : null,
                'logo' => isset($leagueData['logo']) ? (string) $leagueData['logo'] : null,
                'flag' => isset($countryData['flag']) ? (string) $countryData['flag'] : null,
                'current' => isset($leagueData['current']) ? (bool) $leagueData['current'] : true,
                'external_payload' => $footballLeague->response,
                'last_synced_at' => now(),
            ]
        );

        Log::info('FootballSyncService league synchronized', [
            'league_id' => $league->id,
            'provider' => $league->provider,
            'provider_league_id' => $league->provider_league_id,
            'name' => $league->name,
        ]);

        $this->syncLeagueSeasons($league, $footballLeague->response);

        event(new LeagueSynced($league));

        return $league;
    }

    /**
     * @param array<string, mixed> $leagueResponse
     */
    private function syncLeagueSeasons(League $league, array $leagueResponse): void
    {
        $seasons = data_get($leagueResponse, 'seasons', []);
        if (!is_array($seasons)) {
            return;
        }

        foreach ($seasons as $season) {
            if (!is_array($season) || !isset($season['year'])) {
                continue;
            }

            LeagueSeason::updateOrCreate(
                [
                    'league_id' => $league->id,
                    'year' => (int) $season['year'],
                ],
                [
                    'start' => isset($season['start']) ? (string) $season['start'] : null,
                    'end' => isset($season['end']) ? (string) $season['end'] : null,
                    'current' => (bool) ($season['current'] ?? false),
                ]
            );
        }
    }

    /**
     * @return Collection<int, Team>
     */
    public function syncTeams(int $leagueId, int $season): Collection
    {
        $footballTeams = $this->footballDataClient->getTeams($leagueId, $season);
        $league = League::query()
            ->where('provider', 'api_football')
            ->where('provider_league_id', $leagueId)
            ->first();

        $savedTeams = collect();

        foreach ($footballTeams as $footballTeam) {
            if ($footballTeam->errorMessage !== null || $footballTeam->response === null) {
                throw new RuntimeException($footballTeam->errorMessage ?? 'Provider returned no teams data.');
            }

            $teamData = $footballTeam->response['team'] ?? [];
            $venueData = $footballTeam->response['venue'] ?? [];
            $providerTeamId = (int) ($teamData['id'] ?? 0);

            if ($providerTeamId < 1) {
                throw new RuntimeException('Provider returned team without id.');
            }

            $team = Team::updateOrCreate(
                [
                    'provider' => $footballTeam->provider,
                    'provider_team_id' => $providerTeamId,
                ],
                [
                    'league_id' => $league?->id,
                    'season' => $footballTeam->season,
                    'name' => (string) ($teamData['name'] ?? ''),
                    'code' => isset($teamData['code']) ? (string) $teamData['code'] : null,
                    'country_name' => isset($teamData['country']) ? (string) $teamData['country'] : null,
                    'founded' => isset($teamData['founded']) ? (int) $teamData['founded'] : null,
                    'national' => isset($teamData['national']) ? (bool) $teamData['national'] : false,
                    'logo' => isset($teamData['logo']) ? (string) $teamData['logo'] : null,
                    'venue_provider_id' => isset($venueData['id']) ? (int) $venueData['id'] : null,
                    'venue_name' => isset($venueData['name']) ? (string) $venueData['name'] : null,
                    'venue_address' => isset($venueData['address']) ? (string) $venueData['address'] : null,
                    'venue_city' => isset($venueData['city']) ? (string) $venueData['city'] : null,
                    'venue_capacity' => isset($venueData['capacity']) ? (int) $venueData['capacity'] : null,
                    'venue_surface' => isset($venueData['surface']) ? (string) $venueData['surface'] : null,
                    'venue_image' => isset($venueData['image']) ? (string) $venueData['image'] : null,
                    'is_active' => true,
                    'external_payload' => $footballTeam->response,
                    'last_synced_at' => now(),
                ]
            );

            Log::info('FootballSyncService team synchronized', [
                'team_id' => $team->id,
                'provider' => $team->provider,
                'provider_team_id' => $team->provider_team_id,
                'league_id' => $team->league_id,
                'season' => $team->season,
                'name' => $team->name,
            ]);

            event(new TeamSynced($team));

            $savedTeams->push($team);
        }

        Log::info('FootballSyncService teams synchronization completed', [
            'provider_league_id' => $leagueId,
            'league_id' => $league?->id,
            'season' => $season,
            'teams_count' => $savedTeams->count(),
        ]);

        if ($league !== null) {
            event(new LeagueTeamsSynced($league, $season));
        }

        return $savedTeams;
    }

    /**
     * @return Collection<int, Fixture>
     */
    public function syncFixtures(int $leagueId, int $season): Collection
    {
        $footballFixtures = $this->footballDataClient->getFixtures($leagueId, $season);

        $savedFixtures = collect();

        foreach ($footballFixtures as $footballFixture) {
            if ($footballFixture->errorMessage !== null || $footballFixture->response === null) {
                throw new RuntimeException($footballFixture->errorMessage ?? 'Provider returned no fixtures data.');
            }

            $fixtureData = is_array($footballFixture->response['fixture'] ?? null) ? $footballFixture->response['fixture'] : [];
            $leagueData = is_array($footballFixture->response['league'] ?? null) ? $footballFixture->response['league'] : [];
            $teamsData = is_array($footballFixture->response['teams'] ?? null) ? $footballFixture->response['teams'] : [];
            $goalsData = is_array($footballFixture->response['goals'] ?? null) ? $footballFixture->response['goals'] : [];
            $statusData = is_array($fixtureData['status'] ?? null) ? $fixtureData['status'] : [];
            $venueData = is_array($fixtureData['venue'] ?? null) ? $fixtureData['venue'] : [];

            $providerFixtureId = (int) ($fixtureData['id'] ?? 0);
            if ($providerFixtureId < 1) {
                throw new RuntimeException('Provider returned fixture without id.');
            }

            $providerLeagueId = (int) ($leagueData['id'] ?? $leagueId);
            $league = League::query()
                ->where('provider', $footballFixture->provider)
                ->where('provider_league_id', $providerLeagueId)
                ->first();

            if ($league === null) {
                Log::warning('FootballSyncService skipping fixture because league does not exist locally', [
                    'provider_fixture_id' => $providerFixtureId,
                    'provider_league_id' => $providerLeagueId,
                    'season' => $season,
                ]);

                continue;
            }

            $homeProviderTeamId = (int) data_get($teamsData, 'home.id', 0);
            $awayProviderTeamId = (int) data_get($teamsData, 'away.id', 0);

            $homeTeam = $homeProviderTeamId > 0
                ? Team::query()
                    ->where('provider', $footballFixture->provider)
                    ->where('provider_team_id', $homeProviderTeamId)
                    ->first()
                : null;

            $awayTeam = $awayProviderTeamId > 0
                ? Team::query()
                    ->where('provider', $footballFixture->provider)
                    ->where('provider_team_id', $awayProviderTeamId)
                    ->first()
                : null;

            $statusShort = isset($statusData['short']) ? (string) $statusData['short'] : null;
            $isFinished = in_array($statusShort, self::FINISHED_STATUS_SHORTS, true);
            $existingFixture = Fixture::query()
                ->where('provider', $footballFixture->provider)
                ->where('provider_fixture_id', $providerFixtureId)
                ->first(['id', 'finished_at']);
            $wasAlreadyFinished = $existingFixture?->finished_at !== null;
            $finishedAt = $existingFixture?->finished_at;
            if ($isFinished && $finishedAt === null) {
                $finishedAt = now();
            }

            $fixture = Fixture::updateOrCreate(
                [
                    'provider' => $footballFixture->provider,
                    'provider_fixture_id' => $providerFixtureId,
                ],
                [
                    'league_id' => $league->id,
                    'season' => isset($leagueData['season']) ? (int) $leagueData['season'] : $season,
                    'round' => isset($leagueData['round']) ? (string) $leagueData['round'] : null,
                    'referee' => isset($fixtureData['referee']) ? (string) $fixtureData['referee'] : null,
                    'timezone' => isset($fixtureData['timezone']) ? (string) $fixtureData['timezone'] : null,
                    'fixture_date' => isset($fixtureData['date']) ? (string) $fixtureData['date'] : null,
                    'timestamp' => isset($fixtureData['timestamp']) ? (int) $fixtureData['timestamp'] : null,
                    'venue_provider_id' => isset($venueData['id']) ? (int) $venueData['id'] : null,
                    'venue_name' => isset($venueData['name']) ? (string) $venueData['name'] : null,
                    'venue_city' => isset($venueData['city']) ? (string) $venueData['city'] : null,
                    'status_long' => isset($statusData['long']) ? (string) $statusData['long'] : null,
                    'status_short' => $statusShort,
                    'status_elapsed' => isset($statusData['elapsed']) ? (int) $statusData['elapsed'] : null,
                    'home_team_id' => $homeTeam?->id,
                    'away_team_id' => $awayTeam?->id,
                    'home_goals' => isset($goalsData['home']) ? (int) $goalsData['home'] : null,
                    'away_goals' => isset($goalsData['away']) ? (int) $goalsData['away'] : null,
                    'is_active' => !$isFinished,
                    'external_payload' => $footballFixture->response,
                    'finished_at' => $finishedAt,
                    'last_synced_at' => now(),
                ]
            );

            Log::info('FootballSyncService fixture synchronized', [
                'fixture_id' => $fixture->id,
                'provider_fixture_id' => $fixture->provider_fixture_id,
                'league_id' => $fixture->league_id,
                'season' => $fixture->season,
                'status_short' => $fixture->status_short,
            ]);

            if ($isFinished && !$wasAlreadyFinished) {
                event(new FixtureFinished(
                    $league,
                    (int) $fixture->season,
                    (int) $fixture->provider_fixture_id
                ));
            }

            $homePayload = is_array($teamsData['home'] ?? null) ? $teamsData['home'] : [];
            $awayPayload = is_array($teamsData['away'] ?? null) ? $teamsData['away'] : [];

            if ($homeTeam !== null) {
                FixtureTeamStat::updateOrCreate(
                    [
                        'fixture_id' => $fixture->id,
                        'team_id' => $homeTeam->id,
                    ],
                    [
                        'provider' => $footballFixture->provider,
                        'is_home' => true,
                        'winner' => isset($homePayload['winner']) ? (bool) $homePayload['winner'] : null,
                        'goals' => isset($goalsData['home']) ? (int) $goalsData['home'] : null,
                        'external_payload' => [
                            'fixture' => $fixtureData,
                            'team' => $homePayload,
                            'goals' => ['home' => $goalsData['home'] ?? null],
                        ],
                        'last_synced_at' => now(),
                    ]
                );
            }

            if ($awayTeam !== null) {
                FixtureTeamStat::updateOrCreate(
                    [
                        'fixture_id' => $fixture->id,
                        'team_id' => $awayTeam->id,
                    ],
                    [
                        'provider' => $footballFixture->provider,
                        'is_home' => false,
                        'winner' => isset($awayPayload['winner']) ? (bool) $awayPayload['winner'] : null,
                        'goals' => isset($goalsData['away']) ? (int) $goalsData['away'] : null,
                        'external_payload' => [
                            'fixture' => $fixtureData,
                            'team' => $awayPayload,
                            'goals' => ['away' => $goalsData['away'] ?? null],
                        ],
                        'last_synced_at' => now(),
                    ]
                );
            }

            $savedFixtures->push($fixture);
        }

        Log::info('FootballSyncService fixtures synchronization completed', [
            'provider_league_id' => $leagueId,
            'season' => $season,
            'fixtures_count' => $savedFixtures->count(),
        ]);

        return $savedFixtures;
    }

    /**
     * @return Collection<int, LeagueStanding>
     */
    public function syncStandings(int $leagueId, int $season): Collection
    {
        $footballStanding = $this->footballDataClient->getStandings($leagueId, $season);

        if ($footballStanding->errorMessage !== null || $footballStanding->response === null) {
            throw new RuntimeException($footballStanding->errorMessage ?? 'Provider returned no standings data.');
        }

        $leagueData = is_array($footballStanding->response['league'] ?? null) ? $footballStanding->response['league'] : [];
        $providerLeagueId = (int) ($leagueData['id'] ?? $leagueId);

        $league = League::query()
            ->where('provider', $footballStanding->provider)
            ->where('provider_league_id', $providerLeagueId)
            ->first();

        if ($league === null) {
            Log::warning('FootballSyncService skipping standings because league does not exist locally', [
                'provider_league_id' => $providerLeagueId,
                'season' => $season,
            ]);

            return collect();
        }

        $standingsGroups = $leagueData['standings'] ?? [];
        if (!is_array($standingsGroups)) {
            $standingsGroups = [];
        }

        $savedStandings = collect();
        $savedRowsCount = 0;

        foreach ($standingsGroups as $groupRows) {
            if (!is_array($groupRows)) {
                continue;
            }

            $firstRow = isset($groupRows[0]) && is_array($groupRows[0]) ? $groupRows[0] : [];
            $standingGroup = isset($firstRow['group']) ? (string) $firstRow['group'] : null;
            $standingDescription = isset($firstRow['description']) ? (string) $firstRow['description'] : null;
            $standingForm = isset($firstRow['form']) ? (string) $firstRow['form'] : null;

            $standing = LeagueStanding::updateOrCreate(
                [
                    'provider' => $footballStanding->provider,
                    'league_id' => $league->id,
                    'season' => isset($leagueData['season']) ? (int) $leagueData['season'] : $season,
                    'standing_group' => $standingGroup,
                    'standing_stage' => null,
                ],
                [
                    'standing_type' => isset($leagueData['type']) ? (string) $leagueData['type'] : null,
                    'form' => $standingForm,
                    'description' => $standingDescription,
                    'external_payload' => [
                        'league' => $leagueData,
                        'group_rows' => $groupRows,
                    ],
                    'last_synced_at' => now(),
                ]
            );

            $savedStandings->push($standing);

            foreach ($groupRows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $providerTeamId = (int) data_get($row, 'team.id', 0);
                if ($providerTeamId < 1) {
                    continue;
                }

                $team = Team::query()
                    ->where('provider', $footballStanding->provider)
                    ->where('provider_team_id', $providerTeamId)
                    ->first();

                if ($team === null) {
                    Log::warning('FootballSyncService skipping standing row because team does not exist locally', [
                        'standing_id' => $standing->id,
                        'provider_team_id' => $providerTeamId,
                        'provider_league_id' => $providerLeagueId,
                        'season' => $season,
                    ]);

                    continue;
                }

                $all = is_array($row['all'] ?? null) ? $row['all'] : [];
                $home = is_array($row['home'] ?? null) ? $row['home'] : [];
                $away = is_array($row['away'] ?? null) ? $row['away'] : [];
                $goalsDiff = data_get($row, 'goalsDiff');

                LeagueStandingRow::updateOrCreate(
                    [
                        'standing_id' => $standing->id,
                        'team_id' => $team->id,
                    ],
                    [
                        'rank_position' => (int) ($row['rank'] ?? 0),
                        'points' => isset($row['points']) ? (int) $row['points'] : null,
                        'goals_diff' => is_numeric($goalsDiff) ? (int) $goalsDiff : null,
                        'matches_played' => isset($all['played']) ? (int) $all['played'] : null,
                        'matches_win' => isset($all['win']) ? (int) $all['win'] : null,
                        'matches_draw' => isset($all['draw']) ? (int) $all['draw'] : null,
                        'matches_lose' => isset($all['lose']) ? (int) $all['lose'] : null,
                        'goals_for' => isset($all['goals']['for']) ? (int) $all['goals']['for'] : null,
                        'goals_against' => isset($all['goals']['against']) ? (int) $all['goals']['against'] : null,
                        'row_form' => isset($row['form']) ? (string) $row['form'] : null,
                        'status' => isset($row['status']) ? (string) $row['status'] : null,
                        'row_description' => isset($row['description']) ? (string) $row['description'] : null,
                        'home_played' => isset($home['played']) ? (int) $home['played'] : null,
                        'home_win' => isset($home['win']) ? (int) $home['win'] : null,
                        'home_draw' => isset($home['draw']) ? (int) $home['draw'] : null,
                        'home_lose' => isset($home['lose']) ? (int) $home['lose'] : null,
                        'home_goals_for' => isset($home['goals']['for']) ? (int) $home['goals']['for'] : null,
                        'home_goals_against' => isset($home['goals']['against']) ? (int) $home['goals']['against'] : null,
                        'away_played' => isset($away['played']) ? (int) $away['played'] : null,
                        'away_win' => isset($away['win']) ? (int) $away['win'] : null,
                        'away_draw' => isset($away['draw']) ? (int) $away['draw'] : null,
                        'away_lose' => isset($away['lose']) ? (int) $away['lose'] : null,
                        'away_goals_for' => isset($away['goals']['for']) ? (int) $away['goals']['for'] : null,
                        'away_goals_against' => isset($away['goals']['against']) ? (int) $away['goals']['against'] : null,
                        'raw_row_payload' => $row,
                        'last_synced_at' => now(),
                    ]
                );

                $savedRowsCount++;

                Log::info('FootballSyncService standing row synchronized', [
                    'standing_id' => $standing->id,
                    'team_id' => $team->id,
                    'provider_team_id' => $team->provider_team_id,
                    'rank' => (int) ($row['rank'] ?? 0),
                    'points' => isset($row['points']) ? (int) $row['points'] : null,
                ]);
            }
        }

        Log::info('FootballSyncService standings synchronization completed', [
            'provider_league_id' => $providerLeagueId,
            'league_id' => $league->id,
            'season' => $season,
            'standings_count' => $savedStandings->count(),
            'standing_rows_count' => $savedRowsCount,
        ]);

        return $savedStandings;
    }

    /**
     * @return Collection<int, Player>
     */
    public function syncPlayers(int $teamId, int $season): Collection
    {
        $footballPlayers = $this->footballDataClient->getPlayers($teamId, $season);
        $team = Team::query()
            ->where('provider', 'api_football')
            ->where('provider_team_id', $teamId)
            ->first();

        if ($team === null) {
            throw new RuntimeException('Team not found for provider team id ' . $teamId . '.');
        }

        $savedPlayers = collect();
        $savedStatsCount = 0;

        foreach ($footballPlayers as $footballPlayer) {
            if ($footballPlayer->errorMessage !== null || $footballPlayer->response === null) {
                throw new RuntimeException($footballPlayer->errorMessage ?? 'Provider returned no players data.');
            }

            $playerData = $footballPlayer->response['player'] ?? [];
            $providerPlayerId = (int) ($playerData['id'] ?? 0);

            if ($providerPlayerId < 1) {
                throw new RuntimeException('Provider returned player without id.');
            }

            $birthData = is_array($playerData['birth'] ?? null) ? $playerData['birth'] : [];
            $birthDate = isset($birthData['date']) && is_string($birthData['date']) ? $birthData['date'] : null;
            $fullName = isset($playerData['name']) ? (string) $playerData['name'] : trim(
                (string) ($playerData['firstname'] ?? '') . ' ' . (string) ($playerData['lastname'] ?? '')
            );
            $rawAge = isset($playerData['age']) ? (int) $playerData['age'] : null;
            $safeAge = $rawAge !== null && $rawAge >= 1 && $rawAge <= 99 ? $rawAge : null;

            if ($rawAge !== null && $safeAge === null) {
                Log::warning('FootballSyncService received invalid player age, storing null', [
                    'provider_player_id' => $providerPlayerId,
                    'raw_age' => $rawAge,
                    'team_id' => $team->id,
                    'season' => $season,
                ]);
            }

            $player = Player::updateOrCreate(
                [
                    'provider' => $footballPlayer->provider,
                    'provider_player_id' => $providerPlayerId,
                ],
                [
                    'firstname' => isset($playerData['firstname']) ? (string) $playerData['firstname'] : null,
                    'lastname' => isset($playerData['lastname']) ? (string) $playerData['lastname'] : null,
                    'full_name' => $fullName !== '' ? $fullName : null,
                    'age' => $safeAge,
                    'birth_date' => $birthDate,
                    'birth_place' => isset($birthData['place']) ? (string) $birthData['place'] : null,
                    'birth_country' => isset($birthData['country']) ? (string) $birthData['country'] : null,
                    'nationality' => isset($playerData['nationality']) ? (string) $playerData['nationality'] : null,
                    'height' => isset($playerData['height']) ? (string) $playerData['height'] : null,
                    'weight' => isset($playerData['weight']) ? (string) $playerData['weight'] : null,
                    'injured' => isset($playerData['injured']) ? (bool) $playerData['injured'] : false,
                    'photo' => isset($playerData['photo']) ? (string) $playerData['photo'] : null,
                    'is_active' => true,
                    'external_payload' => $footballPlayer->response,
                    'last_synced_at' => now(),
                ]
            );

            Log::info('FootballSyncService player synchronized', [
                'player_id' => $player->id,
                'provider_player_id' => $player->provider_player_id,
                'team_id' => $team->id,
                'season' => $season,
                'name' => $player->full_name,
            ]);

            $statistics = $footballPlayer->response['statistics'] ?? [];
            if (!is_array($statistics)) {
                $statistics = [];
            }

            foreach ($statistics as $statistic) {
                if (!is_array($statistic)) {
                    continue;
                }

                $statTeam = is_array($statistic['team'] ?? null) ? $statistic['team'] : [];
                $providerStatTeamId = (int) ($statTeam['id'] ?? 0);
                if ($providerStatTeamId > 0 && $providerStatTeamId !== (int) $team->provider_team_id) {
                    $team = Team::query()
                        ->where('provider', $footballPlayer->provider)
                        ->where('provider_team_id', $providerStatTeamId)
                        ->first() ?? $team;
                }

                $leagueData = is_array($statistic['league'] ?? null) ? $statistic['league'] : [];
                $providerLeagueId = (int) ($leagueData['id'] ?? 0);
                if ($providerLeagueId < 1) {
                    continue;
                }

                $league = League::firstOrCreate(
                    [
                        'provider' => $footballPlayer->provider,
                        'provider_league_id' => $providerLeagueId,
                    ],
                    [
                        'name' => isset($leagueData['name']) ? (string) $leagueData['name'] : 'Unknown league',
                        'type' => 'league',
                        'country_name' => isset($leagueData['country']) ? (string) $leagueData['country'] : null,
                        'logo' => isset($leagueData['logo']) ? (string) $leagueData['logo'] : null,
                        'flag' => isset($leagueData['flag']) ? (string) $leagueData['flag'] : null,
                        'current' => true,
                        'last_synced_at' => now(),
                    ]
                );

                $games = is_array($statistic['games'] ?? null) ? $statistic['games'] : [];
                $goals = is_array($statistic['goals'] ?? null) ? $statistic['goals'] : [];
                $cards = is_array($statistic['cards'] ?? null) ? $statistic['cards'] : [];

                TeamPlayerSeason::updateOrCreate(
                    [
                        'player_id' => $player->id,
                        'team_id' => $team->id,
                        'season' => $season,
                    ],
                    [
                        'provider' => $footballPlayer->provider,
                        'shirt_number' => isset($games['number']) ? (int) $games['number'] : null,
                        'position' => isset($games['position']) ? (string) $games['position'] : null,
                        'is_current' => true,
                        'external_payload' => $statistic,
                        'last_synced_at' => now(),
                    ]
                );

                PlayerLeagueStat::updateOrCreate(
                    [
                        'player_id' => $player->id,
                        'team_id' => $team->id,
                        'league_id' => $league->id,
                        'season' => $season,
                    ],
                    [
                        'provider' => $footballPlayer->provider,
                        'games_appearences' => isset($games['appearences']) ? (int) $games['appearences'] : null,
                        'games_lineups' => isset($games['lineups']) ? (int) $games['lineups'] : null,
                        'games_minutes' => isset($games['minutes']) ? (int) $games['minutes'] : null,
                        'goals_total' => isset($goals['total']) ? (int) $goals['total'] : null,
                        'goals_assists' => isset($goals['assists']) ? (int) $goals['assists'] : null,
                        'cards_yellow' => isset($cards['yellow']) ? (int) $cards['yellow'] : null,
                        'cards_red' => isset($cards['red']) ? (int) $cards['red'] : null,
                        'raw_statistics' => $statistic,
                        'external_payload' => $footballPlayer->response,
                        'last_synced_at' => now(),
                    ]
                );

                $savedStatsCount++;

                Log::info('FootballSyncService player statistics synchronized', [
                    'player_id' => $player->id,
                    'provider_player_id' => $player->provider_player_id,
                    'team_id' => $team->id,
                    'league_id' => $league->id,
                    'season' => $season,
                ]);
            }

            $savedPlayers->push($player);
        }

        Log::info('FootballSyncService players synchronization completed', [
            'provider_team_id' => $teamId,
            'team_id' => $team->id,
            'season' => $season,
            'players_count' => $savedPlayers->count(),
            'player_stats_count' => $savedStatsCount,
        ]);

        return $savedPlayers;
    }
}
