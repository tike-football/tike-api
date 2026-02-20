<?php

namespace App\Services;

use App\Models\League;
use App\Services\FootballDataService\FootballDataClient;
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

        return League::updateOrCreate(
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
    }
}

