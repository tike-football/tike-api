<?php

namespace App\Console\Commands;

use App\Services\FootballFixturesCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CacheFixturesChanges extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'football-data:cache-fixtures-changes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cache fixtures changes data';

    /**
     * Execute the console command.
     */
    public function handle(FootballFixturesCacheService $footballCacheService): int
    {
        Log::info($this->getName() . ' started');

        $fullCacheId = Cache::get(FootballFixturesCacheService::CACHE_FIXTURES_ID);
        if ($fullCacheId === null || trim((string) $fullCacheId) === '') {
            $payload = $footballCacheService->cacheFixtures();

            $this->info('Full fixtures cache was missing. Rebuilt full cache before incremental changes.');
            $this->line('Cache key: ' . FootballFixturesCacheService::CACHE_FIXTURES);
            $this->line('Leagues cached: ' . count($payload['leagues']));
            $this->line('Matches cached: ' . count($payload['matches']));

            return self::SUCCESS;
        }

        if (!$footballCacheService->hasRelevantFixturesForChanges()) {
            Cache::forget(FootballFixturesCacheService::CACHE_FIXTURES_CHANGES);
            $this->info('No relevant fixtures to process for incremental changes.');
            return self::SUCCESS;
        }

        $payload = $footballCacheService->cacheFixtureChanges();

        $this->info('Fixtures changes cache generated.');
        $this->line('Cache key: ' . FootballFixturesCacheService::CACHE_FIXTURES_CHANGES);
        $this->line('Changed matches: ' . count($payload['matches']));
        $this->line('Removed matches: ' . count($payload['meta']['removed_match_ids'] ?? []));

        return self::SUCCESS;
    }
}
