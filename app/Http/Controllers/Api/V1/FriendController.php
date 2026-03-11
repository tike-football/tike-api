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
