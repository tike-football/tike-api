<?php

namespace App\Jobs;

use App\Models\League;
use App\Services\FootballSyncLeagueStructureService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncLeagueStructureJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const MAX_RUNS = 4;

    private const REPEAT_DELAY_MINUTES = 3;

    public function __construct(
        public readonly League $league,
        public readonly int $season,
        public readonly int $providerFixtureId,
        public readonly int $runNumber = 1
    ) {
        $this->onQueue('football-data');
    }

    public function handle(FootballSyncLeagueStructureService $service): void
    {
        Log::info('SyncLeagueStructureJob started', [
            'league_id' => $this->league->id,
            'provider_league_id' => $this->league->provider_league_id,
            'season' => $this->season,
            'provider_fixture_id' => $this->providerFixtureId,
            'run_number' => $this->runNumber,
            'queue' => $this->queue,
        ]);

        $updated = $service->syncLeagueStructure($this->league, $this->season);

        if ($this->runNumber < self::MAX_RUNS) {
            self::dispatch($this->league, $this->season, $this->providerFixtureId, $this->runNumber + 1)
                ->onQueue('football-data')
                ->delay(now()->addMinutes(self::REPEAT_DELAY_MINUTES));
        }

        Log::info('SyncLeagueStructureJob completed successfully', [
            'league_id' => $this->league->id,
            'provider_league_id' => $this->league->provider_league_id,
            'season' => $this->season,
            'provider_fixture_id' => $this->providerFixtureId,
            'run_number' => $this->runNumber,
            'updated' => $updated,
        ]);
    }
}
