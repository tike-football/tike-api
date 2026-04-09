<?php

namespace App\Services;

use App\Models\Pool;
use App\Models\PoolUser;
use App\Models\PoolUserFixture;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PoolService
{
    public function run(int $poolId): Pool
    {
        $pool = Pool::query()->find($poolId);

        if ($pool === null) {
            throw new ModelNotFoundException('Pool not found.');
        }

        $statuses = (array) config('pools.statuses', []);
        $runningStatus = (string) ($statuses[2] ?? 'running');

        $pool->status = $runningStatus;
        $pool->save();

        PoolUserFixture::query()
            ->where('pool_id', $pool->id)
            ->update([
                'is_locked' => true,
            ]);

        return $pool;
    }

    public function initializeApprovedUser(Pool $pool, int $userId): void
    {
        $poolUser = PoolUser::query()
            ->where('pool_id', $pool->id)
            ->where('user_id', $userId)
            ->first();

        if ($poolUser === null) {
            $poolUser = PoolUser::query()->create([
                'pool_id' => $pool->id,
                'user_id' => $userId,
                'approved' => true,
            ]);
        } elseif (!$poolUser->approved) {
            $poolUser->approved = true;
            $poolUser->save();
        }

        if ((string) $pool->scope === 'match') {
            $this->createPoolUserFixture($pool->loadMissing('poolFixtures.fixture'), $userId);
        }
    }

    public function createPoolUserFixture(Pool $pool, int $userId): void
    {
        foreach ($pool->poolFixtures as $poolFixture) {
            $fixture = $poolFixture->fixture;

            if ($fixture === null) {
                continue;
            }

            PoolUserFixture::query()->updateOrCreate(
                [
                    'pool_id' => $pool->id,
                    'user_id' => $userId,
                    'fixture_id' => $fixture->id,
                ],
                [
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
                ]
            );
        }
    }
}
