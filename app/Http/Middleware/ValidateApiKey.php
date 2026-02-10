<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-Key');

        if (!$apiKey) {
            return response()->json([
                'message' => 'API key is required.',
                'error' => 'Missing X-API-Key header'
            ], 401);
        }

        $key = ApiKey::where('key', $apiKey)->first();

        if (!$key) {
            return response()->json([
                'message' => 'Invalid API key.',
                'error' => 'The provided API key is not valid'
            ], 401);
        }

        if (!$key->isValid()) {
            return response()->json([
                'message' => 'API key is inactive or expired.',
                'error' => 'The API key is no longer valid'
            ], 401);
        }

        // Attach API key to request for later use
        $request->attributes->set('api_key', $key);

        // Mark as used (async to avoid slowing down request)
        dispatch(function () use ($key) {
            $key->markAsUsed();
        })->afterResponse();

        return $next($request);
    }
}

