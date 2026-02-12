<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Health check for load balancers / container health checks.
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
    ]);
});
