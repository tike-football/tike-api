<?php

namespace App\Services\FootballDataService;

use App\Services\FootballDataService\ApiFootball\ApiFootballClient;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class FootballDataManager
{
    public function __construct(
        private readonly ?FootballDataClient $client = null,
    ) {
    }

    public function getLeague(int $id, int $season): FootballDataLeague
    {
        return $this->client()->getLeague($id, $season);
    }

    /**
     * @return Collection<int, FootballDataTeam>
     */
    public function getTeams(int $leagueId, int $season): Collection
    {
        return $this->client()->getTeams($leagueId, $season);
    }

    public function getStandings(int $leagueId, int $season): FootballDataStandings
    {
        return $this->client()->getStandings($leagueId, $season);
    }

    /**
     * @return Collection<int, FootballDataFixture>
     */
    public function getFixtures(int $leagueId, int $season): Collection
    {
        return $this->client()->getFixtures($leagueId, $season);
    }

    /**
     * @return Collection<int, FootballDataPlayer>
     */
    public function getPlayers(int $teamId, int $season): Collection
    {
        return $this->client()->getPlayers($teamId, $season);
    }

    private function client(): FootballDataClient
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $driver = (string) config('football-data.data.default', 'api_football');

        return match ($driver) {
            'api_football' => new ApiFootballClient(
                (string) config('football-data.data.drivers.api_football.base_url', ''),
                (string) config('football-data.data.drivers.api_football.api_key', ''),
            ),
            default => throw new InvalidArgumentException("Unsupported football data driver [{$driver}]"),
        };
    }
}

