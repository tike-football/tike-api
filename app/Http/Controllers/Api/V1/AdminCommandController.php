<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class AdminCommandController extends Controller
{
    public function createFakeUsers(int $count): JsonResponse
    {
        try {
            if ($count <= 0) {
                return response()->json([
                    'message' => 'The count must be greater than 0.',
                ], 422);
            }

            Artisan::call('users:create-fake', [
                'count' => $count,
            ]);

            return response()->json([
                'message' => 'Command executed successfully.',
                'command' => 'users:create-fake',
                'count' => $count,
            ]);
        } catch (\Throwable $e) {
            Log::error(__METHOD__ . ' error: ' . $e->getMessage(), [
                'count' => $count,
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'An error occurred while running the command.',
                'error' => 'Command execution failed. Please try again.',
            ], 500);
        }
    }
}
