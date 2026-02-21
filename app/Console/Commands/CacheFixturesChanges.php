<?php

namespace App\Console\Commands;

use App\Services\FootballFixturesCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

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
