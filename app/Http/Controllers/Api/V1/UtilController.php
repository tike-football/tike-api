<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Util\AvailableAvatarsResponse;
use App\Models\UserAvatar;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class UtilController extends Controller
{
    public function getAvailableAvatars(): JsonResponse
    {
        try {
            $avatars = (array) config('avatars.options', []);
            $user = request()->user();
            if ($user !== null) {
                $userAvatars = UserAvatar::query()
                    ->where('user_id', $user->id)
                    ->orderByDesc('created_at')
                    ->pluck('avatar_path')
                    ->all();
                $avatars = array_values(array_merge($avatars, $userAvatars));
            }

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
