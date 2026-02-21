<?php

namespace Tests\Feature\Events;

use App\Events\FootballData\LeagueTeamsSynced;
use App\Listeners\FootballData\SyncFixtures;
use App\Listeners\FootballData\SyncStandings;
use App\Models\League;
use App\Services\FootballSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LeagueTeamsSyncedEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_fixtures_listener_is_registered(): void
    {
        Event::fake([LeagueTeamsSynced::class]);

        Event::assertListening(
            LeagueTeamsSynced::class,
            SyncFixtures::class
        );
    }

    public function test_sync_standings_listener_is_registered(): void
    {
        Event::fake([LeagueTeamsSynced::class]);

        Event::assertListening(
            LeagueTeamsSynced::class,
            SyncStandings::class
        );
    }

    public function test_sync_fixtures_and_sync_standings_listeners_use_football_data_queue(): void
    {
        $fixturesListener = new SyncFixtures();
        $standingsListener = new SyncStandings();

        $this->assertSame('football-data', $fixturesListener->queue);
        $this->assertSame('football-data', $standingsListener->queue);
    }

    public function test_sync_fixtures_listener_calls_sync_service_with_league_id_and_season(): void
    {
        $league = League::create([
            'provider' => 'api_football',
            'provider_league_id' => 39,
            'name' => 'Premier League',
            'type' => 'league',
        ]);

        $event = new LeagueTeamsSynced($league, 2026);
        $listener = new SyncFixtures();

        $serviceMock = $this->createMock(FootballSyncService::class);
        $serviceMock->expects($this->once())
            ->method('syncFixtures')
            ->with(39, 2026)
            ->willReturn(collect());

        $this->app->instance(FootballSyncService::class, $serviceMock);

        $listener->handle($event);
    }

    public function test_sync_standings_listener_calls_sync_service_with_league_id_and_season(): void
    {
        $league = League::create([
            'provider' => 'api_football',
            'provider_league_id' => 39,
            'name' => 'Premier League',
            'type' => 'league',
        ]);

        $event = new LeagueTeamsSynced($league, 2026);
        $listener = new SyncStandings();

        $serviceMock = $this->createMock(FootballSyncService::class);
        $serviceMock->expects($this->once())
            ->method('syncStandings')
            ->with(39, 2026)
            ->willReturn(collect());

        $this->app->instance(FootballSyncService::class, $serviceMock);

        $listener->handle($event);
    }
}
