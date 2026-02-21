<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FootballCacheServiceController extends Controller
{
    public function cacheFixtures(Request $request): JsonResponse
    {
        try {
            $exitCode = Artisan::call('football-data:cache-fixtures');
            $output = trim((string) Artisan::output());

            if ($exitCode !== 0) {
                throw new RuntimeException('football-data:cache-fixtures command failed with exit code ' . $exitCode);
            }

            return response()->json([
                'message' => 'Fixtures cache command executed successfully.',
                'command' => 'football-data:cache-fixtures',
                'exit_code' => $exitCode,
                'output' => $output,
            ]);
        } catch (\Throwable $e) {
            Log::error(__METHOD__ . ' error: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'An error occurred while caching fixtures.',
                'error' => 'Fixtures cache command failed. Please try again.',
            ], 500);
        }
    }
}
