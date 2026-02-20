<?php

namespace Tests\Unit\Services\FootballDataService;

use App\Services\FootballDataService\FootballDataFixture;
use App\Services\FootballDataService\FootballDataLeague;
use App\Services\FootballDataService\FootballDataPlayer;
use App\Services\FootballDataService\FootballDataStandings;
use App\Services\FootballDataService\FootballDataTeam;
use Tests\TestCase;

class FootballDataObjectsTest extends TestCase
{
    public function test_football_data_league_stores_values(): void
    {
        $object = new FootballDataLeague(
            provider: 'api_football',
            endpoint: 'leagues',
            leagueId: 39,
            season: 2026,
            response: ['league' => ['id' => 39]],
            errorMessage: null,
        );

        $this->assertSame('api_football', $object->provider);
        $this->assertSame('leagues', $object->endpoint);
        $this->assertSame(39, $object->leagueId);
        $this->assertSame(2026, $object->season);
        $this->assertSame(['league' => ['id' => 39]], $object->response);
        $this->assertNull($object->errorMessage);
    }

    public function test_football_data_team_stores_values(): void
    {
        $object = new FootballDataTeam(
            provider: 'api_football',
            endpoint: 'teams',
            teamId: 33,
            leagueId: 39,
            season: 2026,
            response: ['team' => ['id' => 33]],
            errorMessage: null,
        );

        $this->assertSame(33, $object->teamId);
        $this->assertSame(39, $object->leagueId);
        $this->assertSame(2026, $object->season);
    }

    public function test_football_data_standings_stores_values(): void
    {
        $object = new FootballDataStandings(
            provider: 'api_football',
            endpoint: 'standings',
            leagueId: 39,
            season: 2026,
            response: ['league' => ['id' => 39]],
            errorMessage: null,
        );

        $this->assertSame('standings', $object->endpoint);
        $this->assertSame(39, $object->leagueId);
    }

    public function test_football_data_fixture_stores_values(): void
    {
        $object = new FootballDataFixture(
            provider: 'api_football',
            endpoint: 'fixtures',
            fixtureId: 1001,
            leagueId: 39,
            season: 2026,
            response: ['fixture' => ['id' => 1001]],
            errorMessage: null,
        );

        $this->assertSame(1001, $object->fixtureId);
        $this->assertSame(39, $object->leagueId);
    }

    public function test_football_data_player_stores_values(): void
    {
        $object = new FootballDataPlayer(
            provider: 'api_football',
            endpoint: 'players',
            playerId: 2002,
            teamId: 33,
            season: 2026,
            response: ['player' => ['id' => 2002]],
            errorMessage: null,
        );

        $this->assertSame(2002, $object->playerId);
        $this->assertSame(33, $object->teamId);
    }
}

