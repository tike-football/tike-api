<?php

namespace App\Providers;

use App\Services\PoolService;
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
        $this->app->singleton(PoolService::class, static fn () => new PoolService());
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
