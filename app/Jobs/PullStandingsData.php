<?php

namespace App\Jobs;

use App\Services\FootballSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PullStandingsData implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The name of the queue the job should be sent to.
     *
     * @var string
     */
    public $queue = 'football-data';

    public function __construct(
        public readonly int $leagueId,
        public readonly int $season
    ) {
    }

    public function handle(FootballSyncService $footballSyncService): void
    {
        Log::info('PullStandingsData job started', [
            'league_id' => $this->leagueId,
            'season' => $this->season,
            'queue' => $this->queue,
        ]);

        $standings = $footballSyncService->syncStandings($this->leagueId, $this->season);

        Log::info('PullStandingsData job completed successfully', [
            'league_id' => $this->leagueId,
            'season' => $this->season,
            'standings_count' => $standings->count(),
        ]);
    }
}

