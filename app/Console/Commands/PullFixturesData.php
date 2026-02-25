<?php

namespace App\Console\Commands;

use App\Jobs\PullStandingsData;
use App\Models\Fixture;
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
        Log::info($this->getName() . ' started');

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
                $previousStatuses = Fixture::query()
                    ->where('league_id', $league->id)
                    ->where('season', $season)
                    ->pluck('status_short', 'provider_fixture_id')
                    ->map(fn (?string $status): string => (string) $status)
                    ->all();

                $fixtures = $footballSyncService->syncFixtures($leagueId, $season);
                $fixturesCount = $fixtures->count();
                $syncedLeagues++;
                $totalFixtures += $fixturesCount;

                $shouldDispatchStandingsSync = false;
                foreach ($fixtures as $fixture) {
                    $fixtureKey = (string) $fixture->provider_fixture_id;
                    $previousStatus = $previousStatuses[$fixtureKey] ?? null;

                    if ($previousStatus === null || $previousStatus === '') {
                        continue;
                    }

                    if (
                        !$this->isFinishedStatus($previousStatus)
                        && $this->isFinishedStatus($fixture->status_short)
                    ) {
                        $shouldDispatchStandingsSync = true;
                        break;
                    }
                }

                if ($shouldDispatchStandingsSync) {
                    PullStandingsData::dispatch($leagueId, $season)->onQueue('football-data');
                    $this->line("Queued PullStandingsData for league {$leagueId} (season {$season}).");
                }

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

    private function isFinishedStatus(?string $statusShort): bool
    {
        return in_array(strtoupper((string) $statusShort), FootballSyncService::FINISHED_STATUS_SHORTS, true);
    }
}
