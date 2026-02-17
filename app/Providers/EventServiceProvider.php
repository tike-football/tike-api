<?php

namespace App\Providers;

use App\Events\User\PasswordForgotRequested;
use App\Events\User\PasswordUpdated;
use App\Events\User\UserStored;
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
    ];

    /**
     * Disable listener auto-discovery to avoid duplicate registrations.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
