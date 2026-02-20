<?php

namespace App\Services;

use App\Events\FootballData\LeagueSynced;
use App\Models\League;
use App\Models\Team;
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
}
