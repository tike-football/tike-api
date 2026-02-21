<?php

namespace App\Listeners\FootballData;

use App\Events\FootballData\TeamSynced;
use App\Services\FootballSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SyncPlayers implements ShouldQueue
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
    public function handle(TeamSynced $event): void
    {
        try {
            $season = (int) ($event->team->season ?? now()->year);
            $teamId = (int) $event->team->provider_team_id;
            $footballSyncService = app(FootballSyncService::class);

            Log::info('SyncPlayers listener started', [
                'team_id' => $event->team->id,
                'provider_team_id' => $teamId,
                'season' => $season,
                'queue' => $this->queue,
            ]);

            $players = $footballSyncService->syncPlayers($teamId, $season);

            Log::info('SyncPlayers listener completed successfully', [
                'team_id' => $event->team->id,
                'provider_team_id' => $teamId,
                'season' => $season,
                'players_count' => $players->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('SyncPlayers listener failed', [
                'team_id' => $event->team->id ?? null,
                'provider_team_id' => $event->team->provider_team_id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
