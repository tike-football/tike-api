<?php

namespace App\Listeners\FootballData;

use App\Events\FootballData\LeagueSynced;
use App\Services\FootballSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SyncTeams implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The name of the queue the job should be sent to.
     *
     * @var string
     */
    public $queue = 'football-data';

    /**
     * Handle the event.
     */
    public function handle(LeagueSynced $event): void
    {
        try {
            $season = (int) (data_get($event->league->external_payload, 'seasons.0.year') ?? now()->year);
            $leagueId = (int) $event->league->provider_league_id;
            $footballSyncService = app(FootballSyncService::class);

            Log::info('SyncTeams listener started', [
                'league_id' => $event->league->id,
                'provider_league_id' => $leagueId,
                'season' => $season,
                'queue' => $this->queue,
            ]);

            $teams = $footballSyncService->syncTeams($leagueId, $season);

            Log::info('SyncTeams listener completed successfully', [
                'league_id' => $event->league->id,
                'provider_league_id' => $leagueId,
                'season' => $season,
                'teams_count' => $teams->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('SyncTeams listener failed', [
                'league_id' => $event->league->id ?? null,
                'provider_league_id' => $event->league->provider_league_id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
