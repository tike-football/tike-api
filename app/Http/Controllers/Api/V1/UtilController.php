<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Util\AvailableAvatarsResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class UtilController extends Controller
{
    public function getAvailableAvatars(): JsonResponse
    {
        try {
            $avatars = (array) config('avatars.options', []);

            return response()->json([
                'available_avatars' => AvailableAvatarsResponse::make($avatars)->resolve(),
            ]);
        } catch (\Throwable $e) {
            Log::error(__METHOD__ . ' error: ' . $e->getMessage(), [
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'An error occurred while retrieving available avatars.',
                'error' => 'Available avatars retrieval failed. Please try again.',
            ], 500);
        }
    }
}
