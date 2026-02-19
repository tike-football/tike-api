<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withEvents(discover: false)
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'client' => \Laravel\Passport\Http\Middleware\EnsureClientIsResourceOwner::class,
            'scope' => \Laravel\Passport\Http\Middleware\CheckTokenForAnyScope::class,
            'auth' => \App\Http\Middleware\Authenticate::class,
            'api.key' => \App\Http\Middleware\ValidateApiKey::class,
        ]);
        
        $middleware->group('api', [
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, $request) {
            return response()->json([
                'message' => 'Unauthenticated.'
            ], 401);
        });

        $exceptions->render(function (AuthorizationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'This action is unauthorized.',
                ], 403);
            }
        });

        $exceptions->render(function (HttpException $e, $request) {
            if ($request->is('api/*') && $e->getStatusCode() === 403) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'Forbidden.',
                ], 403);
            }
        });
    })->create();
