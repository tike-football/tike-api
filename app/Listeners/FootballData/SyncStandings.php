<?php

namespace App\Listeners\FootballData;

use App\Events\FootballData\LeagueTeamsSynced;
use App\Services\FootballSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SyncStandings implements ShouldQueue
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
    public function handle(LeagueTeamsSynced $event): void
    {
        try {
            $leagueId = (int) $event->league->provider_league_id;
            $season = $event->season;
            $footballSyncService = app(FootballSyncService::class);

            Log::info('SyncStandings listener started', [
                'league_id' => $event->league->id,
                'provider_league_id' => $leagueId,
                'season' => $season,
                'queue' => $this->queue,
            ]);

            $standings = $footballSyncService->syncStandings($leagueId, $season);

            Log::info('SyncStandings listener completed successfully', [
                'league_id' => $event->league->id,
                'provider_league_id' => $leagueId,
                'season' => $season,
                'standings_count' => $standings->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('SyncStandings listener failed', [
                'league_id' => $event->league->id ?? null,
                'provider_league_id' => $event->league->provider_league_id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
