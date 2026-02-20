<?php

namespace App\Services\FootballDataService;

use Illuminate\Support\Collection;

interface FootballDataClient
{
    public function getLeague(int $id, int $season): FootballDataLeague;

    /**
     * @return Collection<int, FootballDataTeam>
     */
    public function getTeams(int $leagueId, int $season): Collection;

    public function getStandings(int $leagueId, int $season): FootballDataStandings;

    /**
     * @return Collection<int, FootballDataFixture>
     */
    public function getFixtures(int $leagueId, int $season): Collection;

    /**
     * @return Collection<int, FootballDataPlayer>
     */
    public function getPlayers(int $teamId, int $season): Collection;
}

