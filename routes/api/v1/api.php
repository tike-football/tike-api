<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('health', function () {
    return response()->json([
        'status' => 'ok',
    ]);
});

Route::middleware(['auth:api', 'scope:test:test'])->get('scopes/test', function () {
    return response()->json([
        'scope' => 'test:test',
        'valid' => true,
    ]);
});

Route::prefix('auth')->group(function (): void {
    Route::post('get-token', [AuthController::class, 'getToken']);
    Route::post('sign-up', [AuthController::class, 'signUp']);
    Route::post('verify-email', [AuthController::class, 'verifyEmail'])
        ->middleware(['auth:api', 'scope:user:verify']);
});
