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

    private const MAX_RUNS = 5;

    private const REPEAT_DELAY_MINUTES = 2;

    public function __construct(
        public readonly int $leagueId,
        public readonly int $season,
        public readonly int $runNumber = 1
    ) {
        $this->onQueue('football-data');
    }

    public function handle(FootballSyncService $footballSyncService): void
    {
        Log::info('PullStandingsData job started', [
            'league_id' => $this->leagueId,
            'season' => $this->season,
            'run_number' => $this->runNumber,
            'queue' => $this->queue,
        ]);

        $standings = $footballSyncService->syncStandings($this->leagueId, $this->season);

        if ($this->runNumber < self::MAX_RUNS) {
            self::dispatch($this->leagueId, $this->season, $this->runNumber + 1)
                ->onQueue('football-data')
                ->delay(now()->addMinutes(self::REPEAT_DELAY_MINUTES));
        }

        Log::info('PullStandingsData job completed successfully', [
            'league_id' => $this->leagueId,
            'season' => $this->season,
            'run_number' => $this->runNumber,
            'standings_count' => $standings->count(),
        ]);
    }
}
