<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\User\UserResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    /**
     * Get the authenticated user.
     */
    public function get(Request $request): JsonResponse
    {
        try {
            $user = $request->user()->loadMissing('settings');

            return response()->json([
                'user' => UserResponse::make($user)->resolve(),
            ]);
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ' error: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'An error occurred while retrieving the user profile.',
                'error' => 'User profile retrieval failed. Please try again.',
            ], 500);
        }
    }
}
