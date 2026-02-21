<?php

namespace App\Console\Commands;

use App\Services\FootballFixturesCacheService;
use Illuminate\Console\Command;

class CacheFixtures extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'football-data:cache-fixtures';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cache fixtures data';

    /**
     * Execute the console command.
     */
    public function handle(FootballFixturesCacheService $footballCacheService): int
    {
        $payload = $footballCacheService->cacheFixtures();

        $this->info('Fixtures cache generated.');
        $this->line('Cache key: ' . FootballFixturesCacheService::CACHE_FIXTURES);
        $this->line('Leagues cached: ' . count($payload['leagues']));
        $this->line('Matches cached: ' . count($payload['matches']));

        return self::SUCCESS;
    }
}
