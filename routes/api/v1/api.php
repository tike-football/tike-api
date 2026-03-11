<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CountryController;
use App\Http\Controllers\Api\V1\FriendController;
use App\Http\Controllers\Api\V1\FootballCacheServiceController;
use App\Http\Controllers\Api\V1\FootballDataController;
use App\Http\Controllers\Api\V1\FootballDataServiceController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\UtilController;
use Illuminate\Support\Facades\Route;

// Health check - No API key required (for monitoring)
Route::get('health', function () {
    return response()->json([
        'status' => 'ok',
    ]);
});

// Test endpoint with scopes - Requires both API key and authentication
Route::middleware(['api.key', 'auth:api', 'scope:test:test'])->get('scopes/test', function () {
    return response()->json([
        'scope' => 'test:test',
        'valid' => true,
    ]);
});

// Public endpoints - Require API key but no authentication
Route::middleware(['api.key'])->group(function (): void {
    // Countries endpoints
    Route::prefix('countries')->group(function (): void {
        Route::get('/', [CountryController::class, 'index']);
        Route::get('/{code}', [CountryController::class, 'show']);
    });

    // Auth endpoints (public but need API key)
    Route::prefix('auth')->group(function (): void {
        Route::post('get-token', [AuthController::class, 'getToken']);
        Route::post('sign-up', [AuthController::class, 'signUp']);
        Route::post('password/forgot', [AuthController::class, 'forgotPassword']);
    });
});

// Protected endpoints - Require both API key and authentication
Route::middleware(['api.key', 'auth:api'])->prefix('auth')->group(function (): void {
    Route::post('verify-email', [AuthController::class, 'verifyEmail'])
        ->middleware(['scope:user:verify']);

    Route::post('refresh-token', [AuthController::class, 'refreshToken'])
        ->middleware(['scope:user:refresh-token']);
    
    Route::prefix('password')->group(function (): void {
        Route::patch('/', [AuthController::class, 'updatePassword'])
            ->middleware(['scope:user:update-password']);
        Route::post('reset', [AuthController::class, 'resetPassword'])
            ->middleware(['scope:user:recover-password']);
    });
});

// User endpoints - Require API key, authentication and scopes
Route::middleware(['api.key', 'auth:api'])->prefix('user')->group(function (): void {
    Route::get('/', [UserController::class, 'get'])
        ->middleware(['scope:user:get']);

    Route::patch('avatar', [UserController::class, 'updateAvatar'])
        ->middleware(['scope:user:update']);

    Route::post('avatar/upload', [UserController::class, 'uploadAvatar'])
        ->middleware(['scope:user:update']);
});

// Util endpoints - Require API key, authentication and util scope
Route::middleware(['api.key', 'auth:api'])->prefix('util')->group(function (): void {
    Route::middleware(['scope:util:get'])->controller(UtilController::class)->group(function (): void {
        Route::get('get-available-avatars', 'getAvailableAvatars');
    });
});

Route::middleware(['api.key', 'auth:api'])->prefix('friend')->group(function (): void {
    Route::post('add/{user_id}', [FriendController::class, 'add'])
        ->middleware(['scope:friend:add']);

    Route::middleware(['scope:friend:get'])->controller(FriendController::class)->group(function (): void {
        Route::get('/', 'index');
        Route::get('search/{term}', 'search');
    });
});

// Football data endpoints - Require API key, authentication and read scope
Route::middleware(['api.key', 'auth:api'])->prefix('football-data')->group(function (): void {
    Route::middleware(['scope:football-data:get'])->controller(FootballDataController::class)->group(function (): void {
        Route::get('get-fixtures', 'getFixtures');
        Route::get('get-league-structure', 'getLeagueStructure');
    });
});

// Admin football data endpoints - Require API key, authentication and sync scope
Route::middleware(['api.key', 'auth:api'])->prefix('admin')->group(function (): void {
    Route::prefix('football-data')->group(function (): void {
        Route::middleware(['scope:football-data:sync'])->controller(FootballDataServiceController::class)->group(function (): void {
            Route::post('sync-league', 'syncLeague');
            Route::post('sync-league-structure', 'syncLeagueStructure');
        });

        Route::middleware(['scope:football-data:cache'])->controller(FootballCacheServiceController::class)->group(function (): void {
            Route::post('cache-fixtures', 'cacheFixtures');
        });
    });
});
