<?php

namespace App\Providers;

use App\Services\FootballDataService\FootballDataClient;
use App\Services\FootballDataService\FootballDataManager;
use Illuminate\Support\ServiceProvider;

class FootballDataServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FootballDataManager::class, fn () => new FootballDataManager());

        $this->app->bind(FootballDataClient::class, function ($app) {
            return $app->make(FootballDataManager::class)->driver();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return ['football-data'];
    }
}

