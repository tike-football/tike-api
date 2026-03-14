<?php

namespace App\Listeners\FootballData;

use App\Events\FootballData\FixtureFinished;
use App\Jobs\PullStandingsData;
use App\Jobs\SyncLeagueStructureJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SyncLeagueStructure implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * @var string
     */
    public $queue = 'football-data';

    public function handle(FixtureFinished $event): void
    {
        try {
            PullStandingsData::dispatch(
                $event->league->provider_league_id, 
                $event->season, 
                1
            )->onQueue('football-data')->delay(now()->addMinutes(2));

            SyncLeagueStructureJob::dispatch(
                $event->league,
                $event->season,
                $event->providerFixtureId,
                1
            )->onQueue('football-data')->delay(now()->addMinutes(3));

            Log::info('SyncLeagueStructure listener queued jobs', [
                'league_id' => $event->league->id,
                'provider_league_id' => $event->league->provider_league_id,
                'season' => $event->season,
                'provider_fixture_id' => $event->providerFixtureId,
            ]);
        } catch (\Throwable $e) {
            Log::error('SyncLeagueStructure listener failed', [
                'league_id' => $event->league->id ?? null,
                'provider_league_id' => $event->league->provider_league_id ?? null,
                'season' => $event->season ?? null,
                'provider_fixture_id' => $event->providerFixtureId ?? null,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
