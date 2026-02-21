<?php

namespace App\Services;

use App\Events\FootballData\LeagueSynced;
use App\Events\FootballData\TeamSynced;
use App\Models\League;
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

        event(new LeagueSynced($league));

        return $league;
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

        return $savedTeams;
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

            $player = Player::updateOrCreate(
                [
                    'provider' => $footballPlayer->provider,
                    'provider_player_id' => $providerPlayerId,
                ],
                [
                    'firstname' => isset($playerData['firstname']) ? (string) $playerData['firstname'] : null,
                    'lastname' => isset($playerData['lastname']) ? (string) $playerData['lastname'] : null,
                    'full_name' => $fullName !== '' ? $fullName : null,
                    'age' => isset($playerData['age']) ? (int) $playerData['age'] : null,
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
