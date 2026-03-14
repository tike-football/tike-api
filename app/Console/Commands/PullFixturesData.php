<?php

namespace App\Console\Commands;

use App\Jobs\PullStandingsData;
use App\Models\Fixture;
use App\Models\League;
use App\Services\FootballFixturesCacheService;
use App\Services\FootballSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class PullFixturesData extends Command
{
    private const STALE_SYNC_MINUTES = 15;

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

        $activeLeagues = League::query()
            ->where('is_active', true)
            ->with('currentSeason')
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
            $season = $league->currentSeasonYear();
            if ($season === null) {
                $this->line("Skipping league {$leagueId}: no current season configured.");
                continue;
            }

            $hasRelevantFixtures = $footballCacheService->hasRelevantFixturesForChanges($leagueId);

            try {
                $isStaleSync = $this->isLeagueFixturesSyncStale((int) $league->id, $season);

                if (!$hasRelevantFixtures && !$isStaleSync) {
                    $this->line("Skipping league {$leagueId}: no relevant fixtures in current window.");
                    continue;
                }

                if (!$hasRelevantFixtures && $isStaleSync) {
                    $this->line(
                        "League {$leagueId} has no relevant fixtures in current window, forcing sync due stale data (>" . self::STALE_SYNC_MINUTES . ' min).'
                    );
                }

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
                    PullStandingsData::dispatch($leagueId, $season, 1)
                        ->onQueue('football-data')
                        ->delay(now()->addMinutes(2));
                    $this->line("Queued PullStandingsData series for league {$leagueId} (season {$season}).");
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

    private function isFinishedStatus(?string $statusShort): bool
    {
        return in_array(strtoupper((string) $statusShort), FootballSyncService::FINISHED_STATUS_SHORTS, true);
    }

    private function isLeagueFixturesSyncStale(int $leagueLocalId, int $season): bool
    {
        $lastSyncedAt = Fixture::query()
            ->where('league_id', $leagueLocalId)
            ->where('season', $season)
            ->max('last_synced_at');

        if ($lastSyncedAt === null) {
            return true;
        }

        return Carbon::parse((string) $lastSyncedAt)->lte(now()->subMinutes(self::STALE_SYNC_MINUTES));
    }
}
