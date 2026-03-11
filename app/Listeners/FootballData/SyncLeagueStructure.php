<?php

namespace App\Listeners\FootballData;

use App\Events\FootballData\FixtureFinished;
use App\Services\FootballSyncLeagueStructureService;
use DateInterval;
use DateTimeInterface;
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

    /**
     * Delay listener execution by 30 minutes.
     */
    public function withDelay(FixtureFinished $event): DateTimeInterface|DateInterval|int|null
    {
        return now()->addMinutes(6);
    }

    public function handle(FixtureFinished $event): void
    {
        try {
            $updated = app(FootballSyncLeagueStructureService::class)
                ->syncLeagueStructure($event->league, $event->season);

            Log::info('SyncLeagueStructure listener completed', [
                'league_id' => $event->league->id,
                'provider_league_id' => $event->league->provider_league_id,
                'season' => $event->season,
                'provider_fixture_id' => $event->providerFixtureId,
                'updated' => $updated,
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
