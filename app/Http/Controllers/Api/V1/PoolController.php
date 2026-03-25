<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pool\StorePoolRequest;
use App\Http\Requests\Pool\UpdatePoolRequest;
use App\Http\Resources\Api\V1\Pool\PoolResponse;
use App\Models\Fixture;
use App\Models\Pool;
use App\Models\PoolFixture;
use App\Models\PoolUser;
use App\Models\PoolUserFixture;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
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

    public function update(UpdatePoolRequest $request, int $poolId): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user === null) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            $pool = Pool::query()->with('poolFixtures.fixture')->find($poolId);

            if ($pool === null) {
                return response()->json([
                    'message' => 'Pool not found.',
                ], 404);
            }

            if ((int) $pool->owner_id !== (int) $user->id) {
                return response()->json([
                    'message' => 'You cannot update this pool.',
                ], 403);
            }

            $validated = $request->validated();

            DB::transaction(function () use ($pool, $validated): void {
                $requiresJoinCode = (bool) $validated['requires_join_code'];
                $type = (string) $validated['type'];
                $scoreRepeatLimit = (int) $validated['score_repeat_limit'];
                /** @var array<int, int> $userIds */
                $userIds = array_values(array_map('intval', $validated['user_ids']));
                /** @var array<int, string>|null $possibleScoreIds */
                $possibleScoreIds = $validated['possible_score_ids'] ?? null;

                $pool->fill([
                    'name' => (string) $validated['name'],
                    'description' => (string) $validated['description'],
                    'scope' => (string) $validated['scope'],
                    'type' => $type,
                    'accepts_join_requests' => (bool) $validated['accepts_join_requests'],
                    'requires_join_approval' => (bool) $validated['requires_join_approval'],
                    'code' => $requiresJoinCode ? $this->generateJoinCode() : null,
                    'is_active' => (bool) $validated['is_active'],
                    'status' => 'scheduled',
                ]);
                $pool->save();

                $allowsRepeatedScores = $type === 'selected_score';

                foreach ($pool->poolFixtures as $poolFixture) {
                    $poolFixture->update([
                        'allows_repeated_scores' => $allowsRepeatedScores,
                        'score_repeat_limit' => $scoreRepeatLimit,
                        'score_selection_type' => $type,
                        'possible_scores' => $possibleScoreIds,
                    ]);
                }

                PoolUser::query()->where('pool_id', $pool->id)->delete();
                PoolUserFixture::query()->where('pool_id', $pool->id)->delete();

                foreach ($userIds as $userId) {
                    PoolUser::query()->create([
                        'pool_id' => $pool->id,
                        'user_id' => $userId,
                    ]);

                    foreach ($pool->poolFixtures as $poolFixture) {
                        $fixture = $poolFixture->fixture;

                        if ($fixture === null) {
                            continue;
                        }

                        PoolUserFixture::query()->create([
                            'pool_id' => $pool->id,
                            'user_id' => $userId,
                            'fixture_id' => $fixture->id,
                            'league_id' => $fixture->league_id,
                            'season' => $fixture->season,
                            'round' => $fixture->round,
                            'timezone' => $fixture->timezone,
                            'fixture_date' => $fixture->fixture_date,
                            'timestamp' => $fixture->timestamp,
                            'status_long' => $fixture->status_long,
                            'status_short' => $fixture->status_short,
                            'home_team_id' => $fixture->home_team_id,
                            'away_team_id' => $fixture->away_team_id,
                            'home_goals' => null,
                            'away_goals' => null,
                            'finished_at' => $fixture->finished_at,
                            'entry_order' => null,
                            'is_locked' => false,
                        ]);
                    }
                }
            });

            $pool->refresh();
            $pool->loadMissing(
                'poolUsers.user',
                'poolFixtures.fixture.teamStats.team',
                'poolFixtures.fixture.homeTeam',
                'poolFixtures.fixture.awayTeam'
            );

            return response()->json([
                'pool' => PoolResponse::make($pool)->resolve(),
            ]);
        } catch (\Throwable $e) {
            Log::error(__METHOD__ . ' error: ' . $e->getMessage(), [
                'pool_id' => $poolId,
                'user_id' => $request->user()?->id,
                'payload' => $request->except([]),
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'An error occurred while updating the pool.',
                'error' => 'Pool update failed. Please try again.',
            ], 500);
        }
    }

    private function generateJoinCode(): string
    {
        do {
            $code = Str::upper(Str::random(6));
            $code = preg_replace('/[^A-Z0-9]/', 'A', $code) ?? 'AAAAAA';
            $code = substr($code, 0, 6);
        } while (Pool::query()->where('code', $code)->exists());

        return $code;
    }
}
