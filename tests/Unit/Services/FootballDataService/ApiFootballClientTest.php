<?php

namespace Tests\Unit\Services\FootballDataService;

use App\Services\FootballDataService\ApiFootball\ApiFootballClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiFootballClientTest extends TestCase
{
    private ApiFootballClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new ApiFootballClient(
            baseUrl: 'https://v3.football.api-sports.io',
            apiKey: 'test-api-key',
        );
    }

    public function test_get_league_returns_success_object(): void
    {
        Http::fake([
            'https://v3.football.api-sports.io/leagues*' => Http::response([
                'response' => [
                    ['league' => ['id' => 39, 'name' => 'Premier League']],
                ],
            ], 200),
        ]);

        $result = $this->client->getLeague(39, 2026);

        $this->assertSame('api_football', $result->provider);
        $this->assertSame('leagues', $result->endpoint);
        $this->assertSame(39, $result->leagueId);
        $this->assertSame(2026, $result->season);
        $this->assertNull($result->errorMessage);
        $this->assertSame(39, $result->response['league']['id']);
    }

    public function test_get_teams_returns_collection_of_teams(): void
    {
        Http::fake([
            'https://v3.football.api-sports.io/teams*' => Http::response([
                'response' => [
                    ['team' => ['id' => 33, 'name' => 'Man United']],
                    ['team' => ['id' => 40, 'name' => 'Liverpool']],
                ],
            ], 200),
        ]);

        $result = $this->client->getTeams(39, 2026);

        $this->assertCount(2, $result);
        $this->assertSame(33, $result->first()->teamId);
        $this->assertSame(39, $result->first()->leagueId);
        $this->assertNull($result->first()->errorMessage);
    }

    public function test_get_standings_returns_success_object(): void
    {
        Http::fake([
            'https://v3.football.api-sports.io/standings*' => Http::response([
                'response' => [
                    ['league' => ['id' => 39, 'season' => 2026]],
                ],
            ], 200),
        ]);

        $result = $this->client->getStandings(39, 2026);

        $this->assertSame('standings', $result->endpoint);
        $this->assertSame(39, $result->leagueId);
        $this->assertNull($result->errorMessage);
    }

    public function test_get_fixtures_returns_collection_of_fixtures(): void
    {
        Http::fake([
            'https://v3.football.api-sports.io/fixtures*' => Http::response([
                'response' => [
                    ['fixture' => ['id' => 1001], 'league' => ['id' => 39, 'season' => 2026]],
                    ['fixture' => ['id' => 1002], 'league' => ['id' => 39, 'season' => 2026]],
                ],
            ], 200),
        ]);

        $result = $this->client->getFixtures(39, 2026);

        $this->assertCount(2, $result);
        $this->assertSame(1001, $result->first()->fixtureId);
        $this->assertSame(39, $result->first()->leagueId);
        $this->assertNull($result->first()->errorMessage);
    }

    public function test_get_players_returns_collection_of_players(): void
    {
        Http::fake([
            'https://v3.football.api-sports.io/players*' => Http::response([
                'response' => [
                    ['player' => ['id' => 2001, 'name' => 'Player A']],
                    ['player' => ['id' => 2002, 'name' => 'Player B']],
                ],
            ], 200),
        ]);

        $result = $this->client->getPlayers(33, 2026);

        $this->assertCount(2, $result);
        $this->assertSame(2001, $result->first()->playerId);
        $this->assertSame(33, $result->first()->teamId);
        $this->assertNull($result->first()->errorMessage);
    }

    public function test_get_league_returns_error_object_when_api_fails(): void
    {
        Http::fake([
            'https://v3.football.api-sports.io/leagues*' => Http::response([
                'message' => 'Unauthorized',
            ], 401),
        ]);

        $result = $this->client->getLeague(39, 2026);

        $this->assertSame('api_football', $result->provider);
        $this->assertSame('leagues', $result->endpoint);
        $this->assertNull($result->response);
        $this->assertNotNull($result->errorMessage);
        $this->assertStringContainsString('401', $result->errorMessage);
    }

    public function test_get_teams_returns_single_error_item_when_request_throws_exception(): void
    {
        Http::fake(function () {
            throw new \RuntimeException('Network down');
        });

        $result = $this->client->getTeams(39, 2026);

        $this->assertCount(1, $result);
        $this->assertNull($result->first()->response);
        $this->assertSame('Network down', $result->first()->errorMessage);
    }
}

