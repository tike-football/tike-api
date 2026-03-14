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
    public function index(): JsonResponse
    {
        try {
            $authUser = request()->user();

            if ($authUser === null) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            $outgoingIds = $authUser->outgoingFriendRequests()
                ->pluck('friend_id');
            $incomingIds = $authUser->incomingFriendRequests()
                ->pluck('user_id');

            $friendIds = $outgoingIds->intersect($incomingIds)->values();
            $sentRequestIds = $outgoingIds->diff($incomingIds)->values();
            $receivedRequestIds = $incomingIds->diff($outgoingIds)->values();

            return response()->json([
                'total_friends' => $friendIds->count(),
                'total_outgoing_friend_requests' => $sentRequestIds->count(),
                'total_incoming_friend_requests' => $receivedRequestIds->count(),
                'friends' => $this->resolveUsersByIds($friendIds->all()),
                'outgoing_friend_requests' => $this->resolveUsersByIds($sentRequestIds->all()),
                'incoming_friend_requests' => $this->resolveUsersByIds($receivedRequestIds->all()),
            ]);
        } catch (\Throwable $e) {
            Log::error(__METHOD__ . ' error: ' . $e->getMessage(), [
                'auth_user_id' => request()->user()?->id,
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'An error occurred while retrieving friends.',
                'error' => 'Friend retrieval failed. Please try again.',
            ], 500);
        }
    }

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
            $authUser = request()->user();
            $term = trim($term);

            if (mb_strlen($term) < 3) {
                return response()->json([
                    'users' => [],
                ]);
            }

            $verifiedUsers = User::query()
                ->whereNotNull('email_verified_at')
                ->where('role', '!=', 'admin');

            if ($authUser !== null) {
                $verifiedUsers->where('id', '!=', $authUser->id);
            }

            $emailMatch = (clone $verifiedUsers)
                ->whereRaw('LOWER(email) = ?', [Str::lower($term)])
                ->first();

            if ($emailMatch !== null) {
                return response()->json([
                    'users' => [FriendSearchResponse::make($emailMatch)->resolve()],
                ]);
            }

            $normalizedPhone = preg_replace('/\D+/', '', $term) ?? '';
            $lowerTerm = Str::lower($term);
            $isNumericSearch = $normalizedPhone !== '' && preg_match('/^\+?\d+$/', $term) === 1;

            if ($isNumericSearch) {
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

            $users = (clone $verifiedUsers)
                ->where(function ($query) use ($lowerTerm, $normalizedPhone, $isNumericSearch): void {
                    $query->whereRaw('LOWER(name) LIKE ?', ['%' . $lowerTerm . '%'])
                        ->orWhereRaw('LOWER(email) LIKE ?', ['%' . $lowerTerm . '%'])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', ['%' . $lowerTerm . '%'])
                        ->orWhereRaw("LOWER(TRIM(CONCAT(name, ' ', last_name))) LIKE ?", ['%' . $lowerTerm . '%']);

                    if ($isNumericSearch) {
                        $query->orWhere('phone_number', 'like', '%' . $normalizedPhone . '%')
                            ->orWhereRaw("REPLACE(full_phone_number, '+', '') LIKE ?", ['%' . $normalizedPhone . '%']);
                    }
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

    /**
     * @param list<int> $userIds
     * @return array<int, array<string, mixed>>
     */
    private function resolveUsersByIds(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return FriendSearchResponse::collection(
            User::query()
                ->whereIn('id', $userIds)
                ->orderBy('name')
                ->orderBy('last_name')
                ->get()
        )->resolve();
    }
}
