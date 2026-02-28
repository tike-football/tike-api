<?php

namespace App\Console\Commands;

use App\Services\FootballSyncLeagueStructureService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncLeagueStructure extends Command
{
    /**
     * @var string
     */
    protected $signature = 'football-data:sync-league-structure {league_id : Local leagues.id} {season : Season year (e.g. 2026)}';

    /**
     * @var string
     */
    protected $description = 'Sync league season structure JSON from local standings/fixtures data';

    public function handle(FootballSyncLeagueStructureService $service): int
    {
        Log::info($this->getName() . ' started');

        $leagueId = (int) $this->argument('league_id');
        $season = (int) $this->argument('season');

        $updated = $service->syncLeagueStructure($leagueId, $season);

        if ($updated) {
            $this->info("League structure synced for league {$leagueId}, season {$season}.");
        } else {
            $this->warn("No changes applied for league {$leagueId}, season {$season}.");
        }

        return self::SUCCESS;
    }
}
