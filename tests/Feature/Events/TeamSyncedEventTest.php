<?php

namespace Tests\Feature\Events;

use App\Events\FootballData\TeamSynced;
use App\Listeners\FootballData\SyncPlayers;
use App\Models\Team;
use App\Services\FootballSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TeamSyncedEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_players_listener_is_registered(): void
    {
        Event::fake([TeamSynced::class]);

        Event::assertListening(
            TeamSynced::class,
            SyncPlayers::class
        );
    }

    public function test_sync_players_listener_uses_football_data_queue(): void
    {
        $listener = new SyncPlayers();

        $this->assertSame('football-data', $listener->queue);
    }

    public function test_sync_players_listener_calls_sync_service_with_team_id_and_season(): void
    {
        $team = Team::create([
            'provider' => 'api_football',
            'provider_team_id' => 33,
            'season' => 2026,
            'name' => 'Manchester United',
        ]);

        $event = new TeamSynced($team);
        $listener = new SyncPlayers();

        $serviceMock = $this->createMock(FootballSyncService::class);
        $serviceMock->expects($this->once())
            ->method('syncPlayers')
            ->with(33, 2026)
            ->willReturn(collect());

        $this->app->instance(FootballSyncService::class, $serviceMock);

        $listener->handle($event);
    }
}
