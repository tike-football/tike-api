<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Passport::enablePasswordGrant();

        // Remove Laravel's default Registered -> SendEmailVerificationNotification
        // after all providers have completed booting.
        $this->app->booted(function (): void {
            Event::forget(Registered::class);
        });
    }
}
