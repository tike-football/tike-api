<?php

namespace Tests\Feature\Events;

use App\Events\FootballData\LeagueSynced;
use App\Listeners\FootballData\SyncTeams;
use App\Models\League;
use App\Services\FootballSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LeagueSyncedEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_teams_listener_is_registered(): void
    {
        Event::fake([LeagueSynced::class]);

        Event::assertListening(
            LeagueSynced::class,
            SyncTeams::class
        );
    }

    public function test_sync_teams_listener_uses_football_data_queue(): void
    {
        $listener = new SyncTeams();

        $this->assertSame('football-data', $listener->queue);
    }

    public function test_sync_teams_listener_calls_sync_service_with_league_id_and_season(): void
    {
        $league = League::create([
            'provider' => 'api_football',
            'provider_league_id' => 39,
            'name' => 'Premier League',
            'type' => 'league',
            'external_payload' => [
                'seasons' => [
                    ['year' => 2026],
                ],
            ],
        ]);

        $event = new LeagueSynced($league);
        $listener = new SyncTeams();

        $serviceMock = $this->createMock(FootballSyncService::class);
        $serviceMock->expects($this->once())
            ->method('syncTeams')
            ->with(39, 2026)
            ->willReturn(collect());

        $this->app->instance(FootballSyncService::class, $serviceMock);

        $listener->handle($event);
    }
}
