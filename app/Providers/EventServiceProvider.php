<?php

namespace App\Providers;

use App\Events\FootballData\LeagueSynced;
use App\Events\FootballData\LeagueTeamsSynced;
use App\Events\FootballData\FixtureFinished;
use App\Events\FootballData\TeamSynced;
use App\Events\User\PasswordForgotRequested;
use App\Events\User\PasswordUpdated;
use App\Events\User\UserStored;
use App\Listeners\FootballData\SyncPlayers;
use App\Listeners\FootballData\SyncFixtures;
use App\Listeners\FootballData\SyncLeagueStructure;
use App\Listeners\FootballData\SyncStandings;
use App\Listeners\FootballData\SyncTeams;
use App\Listeners\User\SendEmailVerification;
use App\Listeners\User\SendPasswordForgotLink;
use App\Listeners\User\SendPasswordUpdatedNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        UserStored::class => [
            SendEmailVerification::class,
        ],
        PasswordForgotRequested::class => [
            SendPasswordForgotLink::class,
        ],
        PasswordUpdated::class => [
            SendPasswordUpdatedNotification::class,
        ],
        LeagueSynced::class => [
            SyncTeams::class,
        ],
        TeamSynced::class => [
            SyncPlayers::class,
        ],
        LeagueTeamsSynced::class => [
            SyncFixtures::class,
            SyncStandings::class,
        ],
        FixtureFinished::class => [
            SyncLeagueStructure::class,
        ],
    ];

    /**
     * Disable listener auto-discovery to avoid duplicate registrations.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }

    /**
     * Disable default Registered email verification auto-listener.
     * This project uses a custom verification token flow.
     */
    protected function configureEmailVerification(): void
    {
        // Intentionally left blank.
    }
}
