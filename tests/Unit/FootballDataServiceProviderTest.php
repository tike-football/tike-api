<?php

namespace Tests\Unit;

use App\Services\FootballDataService\ApiFootball\ApiFootballClient;
use App\Services\FootballDataService\FootballDataClient;
use App\Services\FootballDataService\FootballDataManager;
use Tests\TestCase;

class FootballDataServiceProviderTest extends TestCase
{
    public function test_football_data_manager_is_registered_as_singleton(): void
    {
        $first = $this->app->make(FootballDataManager::class);
        $second = $this->app->make(FootballDataManager::class);

        $this->assertSame($first, $second);
    }

    public function test_football_data_client_binding_resolves_default_driver_client(): void
    {
        config([
            'football-data.data.default' => 'api_football',
            'football-data.data.drivers.api_football.base_url' => 'https://v3.football.api-sports.io',
            'football-data.data.drivers.api_football.api_key' => 'test-key',
        ]);

        $client = $this->app->make(FootballDataClient::class);

        $this->assertInstanceOf(ApiFootballClient::class, $client);
    }

    public function test_manager_driver_returns_client_implementation(): void
    {
        config([
            'football-data.data.default' => 'api_football',
            'football-data.data.drivers.api_football.base_url' => 'https://v3.football.api-sports.io',
            'football-data.data.drivers.api_football.api_key' => 'test-key',
        ]);

        $manager = $this->app->make(FootballDataManager::class);

        $this->assertInstanceOf(ApiFootballClient::class, $manager->driver());
    }
}

