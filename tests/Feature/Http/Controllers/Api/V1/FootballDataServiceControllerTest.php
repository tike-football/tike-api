<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Events\FootballData\LeagueSynced;
use App\Events\FootballData\LeagueTeamsSynced;
use App\Events\FootballData\TeamSynced;
use App\Models\League;
use App\Models\User;
use App\Services\FootballDataService\FootballDataClient;
use App\Services\FootballDataService\FootballDataFixture;
use App\Services\FootballDataService\FootballDataLeague;
use App\Services\FootballDataService\FootballDataPlayer;
use App\Services\FootballDataService\FootballDataStandings;
use App\Services\FootballDataService\FootballDataTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\WithApiKey;

class FootballDataServiceControllerTest extends TestCase
{
    use RefreshDatabase, WithApiKey;

    public function test_admin_with_scope_can_sync_league(): void
    {
        Event::fake([
            LeagueSynced::class,
            TeamSynced::class,
            LeagueTeamsSynced::class,
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        Passport::actingAs($admin, ['football-data:sync']);

        $this->app->instance(FootballDataClient::class, new FakeFootballDataClient(
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

        $response = $this->postJsonWithApiKey('/api/v1/admin/football-data/sync-league', [
            'league_id' => 39,
            'season' => 2026,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'League synchronized successfully.',
                'league' => [
                    'provider' => 'api_football',
                    'provider_league_id' => 39,
                    'name' => 'Premier League',
                    'season' => 2026,
                ],
            ]);

        $this->assertDatabaseHas('leagues', [
            'provider' => 'api_football',
            'provider_league_id' => 39,
            'name' => 'Premier League',
            'type' => 'league',
            'country_name' => 'England',
            'country_code' => 'GB',
            'is_active' => 0,
        ]);
    }

    public function test_sync_league_requires_api_key(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        Passport::actingAs($admin, ['football-data:sync']);

        $response = $this->postJson('/api/v1/admin/football-data/sync-league', [
            'league_id' => 39,
            'season' => 2026,
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'API key is required.',
            ]);
    }

    public function test_sync_league_requires_bearer_token(): void
    {
        $response = $this->postJsonWithApiKey('/api/v1/admin/football-data/sync-league', [
            'league_id' => 39,
            'season' => 2026,
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_sync_league_requires_scope(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        Passport::actingAs($admin, ['different:scope']);

        $response = $this->postJsonWithApiKey('/api/v1/admin/football-data/sync-league', [
            'league_id' => 39,
            'season' => 2026,
        ]);

        $response->assertStatus(403);
    }

    public function test_sync_league_validates_required_fields(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        Passport::actingAs($admin, ['football-data:sync']);

        $response = $this->postJsonWithApiKey('/api/v1/admin/football-data/sync-league', []);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Validation failed.',
            ])
            ->assertJsonValidationErrors(['league_id', 'season']);
    }

    public function test_sync_league_returns_502_when_provider_fails(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        Passport::actingAs($admin, ['football-data:sync']);

        $this->app->instance(FootballDataClient::class, new FakeFootballDataClient(
            new FootballDataLeague(
                provider: 'api_football',
                endpoint: 'leagues',
                leagueId: 39,
                season: 2026,
                response: null,
                errorMessage: 'Provider unavailable',
            )
        ));

        $response = $this->postJsonWithApiKey('/api/v1/admin/football-data/sync-league', [
            'league_id' => 39,
            'season' => 2026,
        ]);

        $response->assertStatus(502)
            ->assertJson([
                'message' => 'Failed to sync league from provider.',
                'error' => 'Provider unavailable',
            ]);

        $this->assertSame(0, League::count());
    }
}

class FakeFootballDataClient implements FootballDataClient
{
    public function __construct(private readonly FootballDataLeague $leagueResponse)
    {
    }

    public function getLeague(int $id, int $season): FootballDataLeague
    {
        return $this->leagueResponse;
    }

    public function getTeams(int $leagueId, int $season): Collection
    {
        return collect();
    }

    public function getStandings(int $leagueId, int $season): FootballDataStandings
    {
        return new FootballDataStandings('api_football', 'standings', $leagueId, $season, null, 'Not implemented in fake');
    }

    public function getFixtures(int $leagueId, int $season): Collection
    {
        return collect([
            new FootballDataFixture('api_football', 'fixtures', null, $leagueId, $season, null, 'Not implemented in fake'),
        ]);
    }

    public function getPlayers(int $teamId, int $season): Collection
    {
        return collect([
            new FootballDataPlayer('api_football', 'players', null, $teamId, $season, null, 'Not implemented in fake'),
        ]);
    }
}
