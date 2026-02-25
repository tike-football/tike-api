<?php

namespace App\Console\Commands;

use App\Models\League;
use App\Services\FootballFixturesCacheService;
use App\Services\FootballSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PullFixturesData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'football-data:pull-fixtures-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pull fixtures data for active leagues';

    /**
     * Execute the console command.
     */
    public function handle(
        FootballSyncService $footballSyncService,
        FootballFixturesCacheService $footballCacheService
    ): int
    {
        if (!$footballCacheService->hasRelevantFixturesForChanges()) {
            $this->info('No relevant fixtures globally (live, starts in next 5 minutes, or finished in last 5 minutes). Skipping.');
            return self::SUCCESS;
        }

        $activeLeagues = League::query()
            ->where('is_active', true)
            ->orderBy('provider_league_id')
            ->get();

        if ($activeLeagues->isEmpty()) {
            $this->warn('No active leagues found.');
            return self::SUCCESS;
        }

        $this->info('Active leagues found: ' . $activeLeagues->count());

        $syncedLeagues = 0;
        $totalFixtures = 0;
        $failed = 0;

        foreach ($activeLeagues as $league) {
            $leagueId = (int) $league->provider_league_id;
            $season = $this->resolveSeason($league);

            if (!$footballCacheService->hasRelevantFixturesForChanges($leagueId)) {
                $this->line("Skipping league {$leagueId}: no relevant fixtures in current window.");
                continue;
            }

            try {
                $fixtures = $footballSyncService->syncFixtures($leagueId, $season);
                $fixturesCount = $fixtures->count();
                $syncedLeagues++;
                $totalFixtures += $fixturesCount;
                $this->line("Synced fixtures for league {$leagueId} (season {$season}): {$fixturesCount}");
            } catch (\Throwable $e) {
                $failed++;
                Log::error('football-data:pull-fixtures-data failed for league', [
                    'league_id' => $league->id,
                    'provider_league_id' => $leagueId,
                    'season' => $season,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Failed league {$leagueId} (season {$season}): {$e->getMessage()}");
            }
        }

        $this->info("Completed. Leagues synced: {$syncedLeagues}. Fixtures synced: {$totalFixtures}. Failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveSeason(League $league): int
    {
        $seasons = data_get($league->external_payload, 'seasons', []);
        if (!is_array($seasons) || $seasons === []) {
            return now()->year;
        }

        foreach ($seasons as $season) {
            if (is_array($season) && (bool) ($season['current'] ?? false) && isset($season['year'])) {
                return (int) $season['year'];
            }
        }

        $firstYear = data_get($seasons, '0.year');
        if ($firstYear !== null) {
            return (int) $firstYear;
        }

        return now()->year;
    }
}
