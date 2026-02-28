<?php

namespace Tests\Feature\Console;

use App\Services\FootballSyncLeagueStructureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncLeagueStructureCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_league_structure_command_reports_success_when_updated(): void
    {
        $serviceMock = $this->createMock(FootballSyncLeagueStructureService::class);
        $serviceMock->expects($this->once())
            ->method('syncLeagueStructure')
            ->with(195, 2026)
            ->willReturn(true);

        $this->app->instance(FootballSyncLeagueStructureService::class, $serviceMock);

        $this->artisan('football-data:sync-league-structure 195 2026')
            ->expectsOutput('League structure synced for league 195, season 2026.')
            ->assertExitCode(0);
    }

    public function test_sync_league_structure_command_reports_no_changes_when_not_updated(): void
    {
        $serviceMock = $this->createMock(FootballSyncLeagueStructureService::class);
        $serviceMock->expects($this->once())
            ->method('syncLeagueStructure')
            ->with(195, 2026)
            ->willReturn(false);

        $this->app->instance(FootballSyncLeagueStructureService::class, $serviceMock);

        $this->artisan('football-data:sync-league-structure 195 2026')
            ->expectsOutput('No changes applied for league 195, season 2026.')
            ->assertExitCode(0);
    }
}
