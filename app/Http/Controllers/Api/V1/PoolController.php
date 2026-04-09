<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pool\JoinPoolRequest;
use App\Http\Requests\Pool\ReviewPoolJoinRequest;
use App\Http\Requests\Pool\StorePoolRequest;
use App\Http\Requests\Pool\UpdatePoolRequest;
use App\Http\Resources\Api\V1\Pool\PoolDetailResponse;
use App\Http\Resources\Api\V1\Pool\PoolListResponse;
use App\Http\Resources\Api\V1\Pool\PoolResponse;
use App\Models\Fixture;
use App\Models\Pool;
use App\Models\PoolFixture;
use App\Models\PoolUser;
use App\Models\PoolUserFixture;
use App\Services\PoolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PoolController extends Controller
{
    public function __construct(
        private readonly PoolService $poolService
    ) {
    }

    public function index(): JsonResponse
    {
        try {
            $user = request()->user();

            if ($user === null) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            $pools = Pool::query()
                ->with([
                    'group:id,name',
                    'league:id,name,country_name',
                    'leagueSeason:id,league_id,year',
                    'poolFixtures:id,pool_id,fixture_id',
                ])
                ->withCount([
                    'poolUsers as approved_pool_users_count' => fn ($query) => $query->where('approved', true),
                    'poolUsers as pending_pool_users_count' => fn ($query) => $query->where('approved', false),
                ])
                ->where(function ($query) use ($user): void {
                    $query->where('owner_id', $user->id)
                        ->orWhereHas('poolUsers', function ($poolUsersQuery) use ($user): void {
                            $poolUsersQuery
                                ->where('user_id', $user->id)
                                ->where('approved', true);
                        });
                })
                ->orderByDesc('id')
                ->get();

            return response()->json([
                'pools' => PoolListResponse::collection($pools)->resolve(),
            ]);
        } catch (\Throwable $e) {
            Log::error(__METHOD__ . ' error: ' . $e->getMessage(), [
                'user_id' => request()->user()?->id,
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'An error occurred while retrieving pools.',
                'error' => 'Pools retrieval failed. Please try again.',
            ], 500);
        }
    }

    public function show(int $poolId): JsonResponse
    {
        try {
            $user = request()->user();

            if ($user === null) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            $pool = Pool::query()
                ->with([
                    'group.users',
                    'league',
                    'leagueSeason',
                    'poolFixtures.fixture.teamStats.team',
                    'poolFixtures.fixture.homeTeam',
                    'poolFixtures.fixture.awayTeam',
                    'poolUsers.user',
                    'poolUsers.poolUserFixtures',
                ])
                ->find($poolId);

            if ($pool === null) {
                return response()->json([
                    'message' => 'Pool not found.',
                ], 404);
            }

            $isOwner = (int) $pool->owner_id === (int) $user->id;
            $isApprovedMember = $pool->poolUsers
                ->contains(fn ($poolUser) => (int) $poolUser->user_id === (int) $user->id && (bool) $poolUser->approved);

            if (!$isOwner && !$isApprovedMember) {
                return response()->json([
                    'message' => 'You do not belong to this pool.',
                ], 403);
            }

            return response()->json([
                'pool' => PoolDetailResponse::make($pool)->resolve(),
            ]);
        } catch (\Throwable $e) {
            Log::error(__METHOD__ . ' error: ' . $e->getMessage(), [
                'pool_id' => $poolId,
                'user_id' => request()->user()?->id,
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'An error occurred while retrieving the pool.',
                'error' => 'Pool retrieval failed. Please try again.',
            ], 500);
        }
    }

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
                    $this->poolService->initializeApprovedUser($pool, $userId);
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

    public function join(JoinPoolRequest $request, int $poolId): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user === null) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            $pool = Pool::query()->find($poolId);

            if ($pool === null) {
                return response()->json([
                    'message' => 'Pool not found.',
                ], 404);
            }

            $existingPoolUser = PoolUser::query()
                ->where('pool_id', $pool->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existingPoolUser !== null) {
                if ($existingPoolUser->approved) {
                    return response()->json([
                        'message' => 'You are already in this pool.',
                    ], 200);
                }

                return response()->json([
                    'message' => 'Your join request is pending approval.',
                ], 200);
            }

            if (!$pool->accepts_join_requests) {
                return response()->json([
                    'message' => 'This pool is not accepting join requests.',
                ], 403);
            }

            $requestCode = $request->validated('code');
            $requiresApproval = (bool) $pool->requires_join_approval;

            if ($pool->code !== null) {
                if ($requestCode === null || $requestCode !== $pool->code) {
                    return response()->json([
                        'message' => 'The provided join code is invalid.',
                    ], 422);
                }

                DB::transaction(function () use ($pool, $user): void {
                    $this->poolService->initializeApprovedUser($pool, $user->id);
                });

                return response()->json([
                    'message' => 'You have joined the pool successfully.',
                ], 201);
            }

            DB::transaction(function () use ($pool, $user, $requiresApproval): void {
                PoolUser::query()->create([
                    'pool_id' => $pool->id,
                    'user_id' => $user->id,
                    'approved' => !$requiresApproval,
                ]);

                if (!$requiresApproval) {
                    $this->poolService->initializeApprovedUser($pool, $user->id);
                }
            });

            if ($requiresApproval) {
                return response()->json([
                    'message' => 'Your join request has been sent and is pending approval.',
                ], 201);
            }

            return response()->json([
                'message' => 'You have joined the pool successfully.',
            ], 201);
        } catch (\Throwable $e) {
            Log::error(__METHOD__ . ' error: ' . $e->getMessage(), [
                'pool_id' => $poolId,
                'user_id' => $request->user()?->id,
                'payload' => $request->except([]),
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'An error occurred while joining the pool.',
                'error' => 'Pool join failed. Please try again.',
            ], 500);
        }
    }

    public function reviewJoinRequest(ReviewPoolJoinRequest $request, int $poolId): JsonResponse
    {
        try {
            $authUser = $request->user();

            if ($authUser === null) {
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

            if ((int) $pool->owner_id !== (int) $authUser->id) {
                return response()->json([
                    'message' => 'You cannot review join requests for this pool.',
                ], 403);
            }

            $validated = $request->validated();
            $userId = (int) $validated['user_id'];
            $approved = (bool) $validated['approved'];

            $poolUser = PoolUser::query()
                ->where('pool_id', $pool->id)
                ->where('user_id', $userId)
                ->first();

            if ($poolUser === null) {
                return response()->json([
                    'message' => 'The user does not have a pending join request for this pool.',
                ], 404);
            }

            if ($poolUser->approved) {
                return response()->json([
                    'message' => 'The user is already approved in this pool.',
                ], 200);
            }

            if (!$approved) {
                DB::transaction(function () use ($poolUser, $pool, $userId): void {
                    PoolUserFixture::query()
                        ->where('pool_id', $pool->id)
                        ->where('user_id', $userId)
                        ->delete();

                    $poolUser->delete();
                });

                return response()->json([
                    'message' => 'Join request rejected successfully.',
                ], 200);
            }

            DB::transaction(function () use ($poolUser, $pool, $userId): void {
                $this->poolService->initializeApprovedUser($pool, $userId);
            });

            return response()->json([
                'message' => 'Join request approved successfully.',
            ], 200);
        } catch (\Throwable $e) {
            Log::error(__METHOD__ . ' error: ' . $e->getMessage(), [
                'pool_id' => $poolId,
                'user_id' => $request->user()?->id,
                'payload' => $request->except([]),
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'An error occurred while reviewing the join request.',
                'error' => 'Join request review failed. Please try again.',
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
