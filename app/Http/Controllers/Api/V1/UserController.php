<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdateAvatarRequest;
use App\Http\Requests\User\UploadAvatarRequest;
use App\Http\Resources\Api\V1\User\UserResponse;
use App\Models\UserAvatar;
use App\Services\UserAvatarStorageService;
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
        } catch (\Throwable $e) {
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

    /**
     * Update authenticated user's avatar path.
     */
    public function updateAvatar(UpdateAvatarRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $user->avatar_path = (string) $request->validated('avatar_path');
            $user->save();

            $user->loadMissing('settings');

            return response()->json([
                'user' => UserResponse::make($user)->resolve(),
            ]);
        } catch (\Throwable $e) {
            Log::error(__METHOD__ . ' error: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'An error occurred while updating the avatar.',
                'error' => 'Avatar update failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Upload a new avatar for the authenticated user.
     */
    public function uploadAvatar(
        UploadAvatarRequest $request,
        UserAvatarStorageService $storageService
    ): JsonResponse {
        try {
            $user = $request->user();
            $file = $request->file('avatar');

            if ($file === null) {
                return response()->json([
                    'message' => 'The avatar image is required.',
                    'error' => 'Avatar upload failed. Please try again.',
                ], 422);
            }

            $avatarPath = $storageService->store($user, $file);

            $user->avatar_path = $avatarPath;
            $user->save();

            UserAvatar::query()->create([
                'user_id' => $user->id,
                'avatar_path' => $avatarPath,
            ]);

            $oldIds = UserAvatar::query()
                ->where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->skip(3)
                ->take(1000)
                ->pluck('avatar_path', 'id')
                ->all();

            if ($oldIds !== []) {
                UserAvatar::query()->whereIn('id', array_keys($oldIds))->delete();
                foreach ($oldIds as $oldPath) {
                    if (is_string($oldPath) && $oldPath !== '') {
                        $storageService->delete($oldPath);
                    }
                }
            }

            $user->loadMissing('settings');

            return response()->json([
                'user' => UserResponse::make($user)->resolve(),
            ]);
        } catch (\Throwable $e) {
            Log::error(__METHOD__ . ' error: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'An error occurred while uploading the avatar.',
                'error' => 'Avatar upload failed. Please try again.',
            ], 500);
        }
    }
}
