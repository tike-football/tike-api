<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pool\StorePoolRequest;
use App\Http\Resources\Api\V1\Pool\PoolResponse;
use App\Models\Pool;
use App\Models\PoolFixture;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PoolController extends Controller
{
    public function store(StorePoolRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user === null) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            $validated = $request->validated();

            $pool = DB::transaction(function () use ($user, $validated): Pool {
                $data = [
                    'owner_id' => $user->id,
                    'league_id' => $validated['league_id'] ?? null,
                    'league_season_id' => $validated['league_season_id'] ?? null,
                    'group_id' => $validated['group_id'] ?? null,
                    'name' => (string) $validated['name'],
                    'description' => (string) $validated['description'],
                    'scope' => (string) $validated['scope'],
                    'start_phase' => $validated['start_phase'] ?? null,
                    'type' => $validated['type'] ?? null,
                    'status' => 'draft',
                    'is_active' => false,
                ];

                if (array_key_exists('accepts_join_requests', $validated)) {
                    $data['accepts_join_requests'] = $validated['accepts_join_requests'];
                }

                if (array_key_exists('requires_join_approval', $validated)) {
                    $data['requires_join_approval'] = $validated['requires_join_approval'];
                }

                $pool = Pool::query()->create($data);

                if (($validated['scope'] ?? null) === 'match' && isset($validated['fixture_id'])) {
                    PoolFixture::query()->create([
                        'pool_id' => $pool->id,
                        'fixture_id' => (int) $validated['fixture_id'],
                        'allows_repeated_scores' => false,
                        'score_selection_type' => (string) $validated['type'],
                    ]);
                }

                return $pool;
            });

            $pool->loadMissing(
                'group.users',
                'poolFixtures.fixture.teamStats.team',
                'poolFixtures.fixture.homeTeam',
                'poolFixtures.fixture.awayTeam'
            );

            return response()->json([
                'pool' => PoolResponse::make($pool)->resolve(),
            ], 201);
        } catch (\Throwable $e) {
            Log::error(__METHOD__ . ' error: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'payload' => $request->except([]),
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'An error occurred while creating the pool.',
                'error' => 'Pool creation failed. Please try again.',
            ], 500);
        }
    }
}
