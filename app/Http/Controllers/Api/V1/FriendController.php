<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Friend\FriendSearchResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FriendController extends Controller
{
    public function add(int $userId): JsonResponse
    {
        try {
            $authUser = request()->user();

            if ($authUser === null) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            if ($authUser->id === $userId) {
                return response()->json([
                    'message' => 'You cannot add yourself as a friend.',
                ], 422);
            }

            $friendUser = User::query()->find($userId);

            if ($friendUser === null) {
                return response()->json([
                    'message' => 'User not found.',
                ], 404);
            }

            if ($authUser->isFriendWith($friendUser->id)) {
                return response()->json([
                    'message' => 'You are already friends.',
                ]);
            }

            if ($authUser->hasSentFriendRequestTo($friendUser->id)) {
                return response()->json([
                    'message' => 'Friend request has already been sent.',
                ]);
            }

            $authUser->outgoingFriendRequests()->create([
                'user_id' => $authUser->id,
                'friend_id' => $friendUser->id,
            ]);

            return response()->json([
                'message' => $authUser->hasReceivedFriendRequestFrom($friendUser->id)
                    ? 'Friend added.'
                    : 'Friend request sent.',
            ]);
        } catch (\Throwable $e) {
            Log::error(__METHOD__ . ' error: ' . $e->getMessage(), [
                'target_user_id' => $userId,
                'auth_user_id' => request()->user()?->id,
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'An error occurred while adding a friend.',
                'error' => 'Friend add failed. Please try again.',
            ], 500);
        }
    }

    public function search(string $term): JsonResponse
    {
        try {
            $term = trim($term);

            if (mb_strlen($term) < 3) {
                return response()->json([
                    'users' => [],
                ]);
            }

            $verifiedUsers = User::query()->whereNotNull('email_verified_at');

            $emailMatch = (clone $verifiedUsers)
                ->whereRaw('LOWER(email) = ?', [Str::lower($term)])
                ->first();

            if ($emailMatch !== null) {
                return response()->json([
                    'users' => [FriendSearchResponse::make($emailMatch)->resolve()],
                ]);
            }

            $normalizedPhone = preg_replace('/\D+/', '', $term) ?? '';

            if ($normalizedPhone !== '') {
                $phoneMatch = (clone $verifiedUsers)
                    ->where(function ($query) use ($normalizedPhone): void {
                        $query->where('phone_number', $normalizedPhone)
                            ->orWhereRaw("REPLACE(full_phone_number, '+', '') = ?", [$normalizedPhone]);
                    })
                    ->first();

                if ($phoneMatch !== null) {
                    return response()->json([
                        'users' => [FriendSearchResponse::make($phoneMatch)->resolve()],
                    ]);
                }
            }

            $lowerTerm = Str::lower($term);

            $users = (clone $verifiedUsers)
                ->where(function ($query) use ($lowerTerm): void {
                    $query->whereRaw('LOWER(name) LIKE ?', ['%' . $lowerTerm . '%'])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', ['%' . $lowerTerm . '%'])
                        ->orWhereRaw("LOWER(TRIM(CONCAT(name, ' ', last_name))) LIKE ?", ['%' . $lowerTerm . '%']);
                })
                ->orderBy('name')
                ->orderBy('last_name')
                ->limit(10)
                ->get();

            return response()->json([
                'users' => FriendSearchResponse::collection($users)->resolve(),
            ]);
        } catch (\Throwable $e) {
            Log::error(__METHOD__ . ' error: ' . $e->getMessage(), [
                'term' => $term,
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'An error occurred while searching friends.',
                'error' => 'Friend search failed. Please try again.',
            ], 500);
        }
    }
}
