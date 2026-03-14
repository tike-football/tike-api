<?php

namespace Tests\Feature\Events;

use App\Events\FootballData\FixtureFinished;
use App\Jobs\PullStandingsData;
use App\Jobs\SyncLeagueStructureJob;
use App\Listeners\FootballData\SyncLeagueStructure;
use App\Models\League;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
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

    public function test_sync_league_structure_listener_queues_jobs_with_expected_queue_and_delays(): void
    {
        $league = League::create([
            'provider' => 'api_football',
            'provider_league_id' => 39,
            'name' => 'Premier League',
            'type' => 'league',
        ]);

        Bus::fake();

        $listener = new SyncLeagueStructure();
        $event = new FixtureFinished($league, 2026, 1001);

        $listener->handle($event);

        $this->assertSame('football-data', $listener->queue);

        Bus::assertDispatched(PullStandingsData::class, function (PullStandingsData $job): bool {
            return $job->leagueId === 39
                && $job->season === 2026
                && $job->runNumber === 1
                && $job->queue === 'football-data'
                && $job->delay !== null
                && now()->diffInMinutes($job->delay) <= 2;
        });

        Bus::assertDispatched(SyncLeagueStructureJob::class, function (SyncLeagueStructureJob $job) use ($league): bool {
            return $job->league->is($league)
                && $job->season === 2026
                && $job->providerFixtureId === 1001
                && $job->runNumber === 1
                && $job->queue === 'football-data'
                && $job->delay !== null
                && now()->diffInMinutes($job->delay) <= 3;
        });
    }

    public function test_sync_league_structure_listener_dispatches_both_jobs_once(): void
    {
        $league = League::create([
            'provider' => 'api_football',
            'provider_league_id' => 39,
            'name' => 'Premier League',
            'type' => 'league',
        ]);

        Bus::fake();

        (new SyncLeagueStructure())->handle(new FixtureFinished($league, 2026, 1001));

        Bus::assertDispatchedTimes(PullStandingsData::class, 1);
        Bus::assertDispatchedTimes(SyncLeagueStructureJob::class, 1);
    }
}
