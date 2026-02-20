<?php

namespace App\Services\FootballDataService\ApiFootball;

use App\Services\FootballDataService\FootballDataClient;
use App\Services\FootballDataService\FootballDataFixture;
use App\Services\FootballDataService\FootballDataLeague;
use App\Services\FootballDataService\FootballDataPlayer;
use App\Services\FootballDataService\FootballDataStandings;
use App\Services\FootballDataService\FootballDataTeam;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiFootballClient implements FootballDataClient
{
    private const PROVIDER = 'api_football';

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
    ) {
    }

    public function getLeague(int $id, int $season): FootballDataLeague
    {
        $endpoint = 'leagues';
        $result = $this->request($endpoint, [
            'id' => $id,
            'season' => $season,
        ]);

        if ($result['ok']) {
            /** @var array<string, mixed>|null $item */
            $item = $result['response'][0] ?? null;

            return new FootballDataLeague(
                provider: self::PROVIDER,
                endpoint: $endpoint,
                leagueId: $id,
                season: $season,
                response: $item,
                errorMessage: null,
            );
        }

        return new FootballDataLeague(
            provider: self::PROVIDER,
            endpoint: $endpoint,
            leagueId: $id,
            season: $season,
            response: null,
            errorMessage: $result['error'],
        );
    }

    /**
     * @return Collection<int, FootballDataTeam>
     */
    public function getTeams(int $leagueId, int $season): Collection
    {
        $endpoint = 'teams';
        $result = $this->request($endpoint, [
            'league' => $leagueId,
            'season' => $season,
        ]);

        if ($result['ok']) {
            return collect($result['response'])->map(
                fn (array $item): FootballDataTeam => new FootballDataTeam(
                    provider: self::PROVIDER,
                    endpoint: $endpoint,
                    teamId: $item['team']['id'] ?? null,
                    leagueId: $leagueId,
                    season: $season,
                    response: $item,
                    errorMessage: null,
                )
            );
        }

        return collect([
            new FootballDataTeam(
                provider: self::PROVIDER,
                endpoint: $endpoint,
                teamId: null,
                leagueId: $leagueId,
                season: $season,
                response: null,
                errorMessage: $result['error'],
            ),
        ]);
    }

    public function getStandings(int $leagueId, int $season): FootballDataStandings
    {
        $endpoint = 'standings';
        $result = $this->request($endpoint, [
            'league' => $leagueId,
            'season' => $season,
        ]);

        if ($result['ok']) {
            /** @var array<string, mixed>|null $item */
            $item = $result['response'][0] ?? null;

            return new FootballDataStandings(
                provider: self::PROVIDER,
                endpoint: $endpoint,
                leagueId: $leagueId,
                season: $season,
                response: $item,
                errorMessage: null,
            );
        }

        return new FootballDataStandings(
            provider: self::PROVIDER,
            endpoint: $endpoint,
            leagueId: $leagueId,
            season: $season,
            response: null,
            errorMessage: $result['error'],
        );
    }

    /**
     * @return Collection<int, FootballDataFixture>
     */
    public function getFixtures(int $leagueId, int $season): Collection
    {
        $endpoint = 'fixtures';
        $result = $this->request($endpoint, [
            'league' => $leagueId,
            'season' => $season,
        ]);

        if ($result['ok']) {
            return collect($result['response'])->map(
                fn (array $item): FootballDataFixture => new FootballDataFixture(
                    provider: self::PROVIDER,
                    endpoint: $endpoint,
                    fixtureId: $item['fixture']['id'] ?? null,
                    leagueId: $item['league']['id'] ?? $leagueId,
                    season: $item['league']['season'] ?? $season,
                    response: $item,
                    errorMessage: null,
                )
            );
        }

        return collect([
            new FootballDataFixture(
                provider: self::PROVIDER,
                endpoint: $endpoint,
                fixtureId: null,
                leagueId: $leagueId,
                season: $season,
                response: null,
                errorMessage: $result['error'],
            ),
        ]);
    }

    /**
     * @return Collection<int, FootballDataPlayer>
     */
    public function getPlayers(int $teamId, int $season): Collection
    {
        $endpoint = 'players';
        $result = $this->request($endpoint, [
            'team' => $teamId,
            'season' => $season,
        ]);

        if ($result['ok']) {
            return collect($result['response'])->map(
                fn (array $item): FootballDataPlayer => new FootballDataPlayer(
                    provider: self::PROVIDER,
                    endpoint: $endpoint,
                    playerId: $item['player']['id'] ?? null,
                    teamId: $teamId,
                    season: $season,
                    response: $item,
                    errorMessage: null,
                )
            );
        }

        return collect([
            new FootballDataPlayer(
                provider: self::PROVIDER,
                endpoint: $endpoint,
                playerId: null,
                teamId: $teamId,
                season: $season,
                response: null,
                errorMessage: $result['error'],
            ),
        ]);
    }

    /**
     * @param array<string, mixed> $query
     * @return array{ok: bool, response: array<int, array<string, mixed>>, error: string|null}
     */
    private function request(string $endpoint, array $query): array
    {
        $requestUrl = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');

        try {
            $response = Http::withHeaders([
                'x-apisports-key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->timeout(20)->get($requestUrl, $query);

            $responseData = $response->json();

            if ($response->successful() && is_array($responseData)) {
                $items = $responseData['response'] ?? [];

                return [
                    'ok' => true,
                    'response' => is_array($items) ? $items : [],
                    'error' => null,
                ];
            }

            Log::error('ApiFootball API error', [
                'status' => $response->status(),
                'response' => $responseData,
                'request_url' => $requestUrl,
                'query' => $query,
            ]);

            return [
                'ok' => false,
                'response' => [],
                'error' => 'ApiFootball request failed with status ' . $response->status(),
            ];
        } catch (\Throwable $e) {
            Log::error('ApiFootball API exception', [
                'message' => $e->getMessage(),
                'request_url' => $requestUrl,
                'query' => $query,
            ]);

            return [
                'ok' => false,
                'response' => [],
                'error' => $e->getMessage(),
            ];
        }
    }
}

