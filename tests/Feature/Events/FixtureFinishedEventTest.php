<?php

namespace Tests\Feature\Events;

use App\Events\FootballData\FixtureFinished;
use App\Listeners\FootballData\SyncLeagueStructure;
use App\Models\League;
use App\Services\FootballSyncLeagueStructureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class FixtureFinishedEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_league_structure_listener_is_registered(): void
    {
        Event::fake([FixtureFinished::class]);

        Event::assertListening(
            FixtureFinished::class,
            SyncLeagueStructure::class
        );
    }

    public function test_sync_league_structure_listener_uses_football_data_queue_and_has_30_minutes_delay(): void
    {
        $league = League::create([
            'provider' => 'api_football',
            'provider_league_id' => 39,
            'name' => 'Premier League',
            'type' => 'league',
        ]);

        $listener = new SyncLeagueStructure();
        $event = new FixtureFinished($league, 2026, 1001);
        $delay = $listener->withDelay($event);
        $minutes = now()->diffInMinutes(Carbon::instance($delay));

        $this->assertSame('football-data', $listener->queue);
        $this->assertInstanceOf(\DateTimeInterface::class, $delay);
        $this->assertGreaterThanOrEqual(29.9, $minutes);
        $this->assertLessThanOrEqual(30.1, $minutes);
    }

    public function test_sync_league_structure_listener_calls_service(): void
    {
        $league = League::create([
            'provider' => 'api_football',
            'provider_league_id' => 39,
            'name' => 'Premier League',
            'type' => 'league',
        ]);

        $event = new FixtureFinished($league, 2026, 1001);
        $listener = new SyncLeagueStructure();

        $serviceMock = $this->createMock(FootballSyncLeagueStructureService::class);
        $serviceMock->expects($this->once())
            ->method('syncLeagueStructure')
            ->with($league, 2026)
            ->willReturn(true);

        $this->app->instance(FootballSyncLeagueStructureService::class, $serviceMock);

        $listener->handle($event);
    }
}
