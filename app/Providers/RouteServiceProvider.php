<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    protected ?string $namespace = 'App\\Http\\Controllers';

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api/v1')
            ->namespace($this->namespace)
            ->group(base_path('routes/api/v1/api.php'));
    }
}
