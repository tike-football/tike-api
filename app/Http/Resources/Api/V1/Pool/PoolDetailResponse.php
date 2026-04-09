<?php

namespace App\Http\Resources\Api\V1\Pool;

use App\Models\PoolUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PoolDetailResponse extends JsonResource
{
    /**
     * @var array<int, string>
     */
    private const LIVE_STATUS_SHORTS = ['1H', 'HT', '2H', 'ET', 'BT', 'P', 'LIVE', 'INT'];

    /**
     * @var array<int, string>
     */
    private const UPCOMING_STATUS_SHORTS = ['TBD', 'NS'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $authUser = $request->user();
        $isOwner = $authUser !== null && (int) $this->owner_id === (int) $authUser->id;
        $approvedPoolUsers = $this->poolUsers->where('approved', true)->values();
        $pendingPoolUsers = $this->poolUsers->where('approved', false)->values();
        $poolFixture = $this->poolFixtures->first();
        $match = ((string) $this->scope === 'match' && $poolFixture?->fixture !== null)
            ? $this->transformMatch($poolFixture->fixture)
            : null;

        $myPoolUser = $authUser !== null
            ? $approvedPoolUsers->first(fn ($poolUser) => (int) $poolUser->user_id === (int) $authUser->id)
            : null;

        return [
            'pool' => [
                'id' => $this->id,
                'owner_id' => $this->owner_id,
                'name' => $this->name,
                'description' => $this->description,
                'scope' => $this->scope,
                'start_phase' => $this->start_phase,
                'type' => $this->type,
                'accepts_join_requests' => $this->accepts_join_requests,
                'requires_join_approval' => $this->requires_join_approval,
                'code' => $this->code,
                'status' => $this->status,
                'is_active' => $this->is_active,
                'is_owner' => $isOwner,
                'total_approved_users' => $approvedPoolUsers->count(),
                'total_pending_join_requests' => $isOwner ? $pendingPoolUsers->count() : null,
            ],
            'group' => $this->group !== null ? [
                'id' => $this->group->id,
                'image_path' => $this->group->image_path,
                'name' => $this->group->name,
                'description' => $this->group->description,
                'total_users' => $this->group->users()->where('group_user.is_accepted', true)->count(),
            ] : null,
            'league' => $this->league !== null ? [
                'league_id' => $this->league->id,
                'name' => $this->league->name,
                'season' => $this->leagueSeason?->year,
                'league_season_id' => $this->league_season_id,
            ] : null,
            'match' => $match,
            'my_pool_user_fixtures' => $myPoolUser instanceof PoolUser
                ? $this->transformPoolUserFixtures(
                    $myPoolUser->poolUserFixtures->where('pool_id', $this->id)->values()
                )
                : [],
            'approved_users' => $approvedPoolUsers
                ->map(fn (PoolUser $poolUser) => $this->transformPoolUser($poolUser, $authUser?->id, true))
                ->values()
                ->all(),
            'pending_users' => $isOwner
                ? $pendingPoolUsers
                    ->map(fn (PoolUser $poolUser) => $this->transformPoolUser($poolUser, $authUser?->id, false))
                    ->values()
                    ->all()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformMatch(mixed $fixture): array
    {
        $homeTeamId = $fixture->homeTeam?->id;
        $awayTeamId = $fixture->awayTeam?->id;
        $teamStats = [];
        $playersByTeam = [];

        foreach ($fixture->teamStats as $stat) {
            $statTeamId = $stat->team?->id;

            if ($statTeamId === null) {
                continue;
            }

            $teamKey = (string) $statTeamId;
            $teamStats[$teamKey] = [
                'goals' => $stat->goals,
                'winner' => $stat->winner,
                'statistics' => $stat->raw_statistics,
            ];
            $playersByTeam[$teamKey] = [
                'starters' => [],
                'bench' => [],
            ];
        }

        return [
            'id' => (int) $fixture->id,
            'league_id' => (int) $fixture->league_id,
            'season' => (int) $fixture->season,
            'round' => $fixture->round,
            'status' => $this->mapFixtureStatus((string) $fixture->status_short),
            'status_short' => $fixture->status_short,
            'minute' => $fixture->status_elapsed,
            'date' => $fixture->fixture_date?->toIso8601String(),
            'home_team_id' => $homeTeamId,
            'away_team_id' => $awayTeamId,
            'score' => [
                'home' => $fixture->home_goals,
                'away' => $fixture->away_goals,
            ],
            'team_stats' => $teamStats,
            'players' => $playersByTeam,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformPoolUser(PoolUser $poolUser, ?int $authUserId, bool $includeFixtures): array
    {
        $user = $poolUser->user;
        $status = null;

        if ($user !== null && $authUserId !== null && (int) $authUserId !== (int) $user->id) {
            if (request()->user()?->isFriendWith($user->id)) {
                $status = 'friend';
            } elseif (request()->user()?->hasSentFriendRequestTo($user->id)) {
                $status = 'outgoing_friend_request';
            } elseif (request()->user()?->hasReceivedFriendRequestFrom($user->id)) {
                $status = 'incoming_friend_request';
            }
        }

        return [
            'id' => $user?->id,
            'name' => $user?->name,
            'last_name' => $user?->last_name,
            'avatar_url' => $user !== null ? $this->resolveAvatarUrl($user->avatar_path) : null,
            'status' => $status,
            'pool_user_fixtures' => $includeFixtures
                ? $this->transformPoolUserFixtures(
                    $poolUser->poolUserFixtures->where('pool_id', $this->id)->values()
                )
                : [],
        ];
    }

    /**
     * @param Collection<int, mixed> $poolUserFixtures
     * @return array<int, array<string, mixed>>
     */
    private function transformPoolUserFixtures(Collection $poolUserFixtures): array
    {
        return $poolUserFixtures
            ->sortBy('fixture_id')
            ->values()
            ->map(function ($poolUserFixture): array {
                return [
                    'id' => $poolUserFixture->id,
                    'fixture_id' => $poolUserFixture->fixture_id,
                    'league_id' => $poolUserFixture->league_id,
                    'season' => $poolUserFixture->season,
                    'round' => $poolUserFixture->round,
                    'timezone' => $poolUserFixture->timezone,
                    'fixture_date' => $poolUserFixture->fixture_date?->toIso8601String(),
                    'timestamp' => $poolUserFixture->timestamp,
                    'status_long' => $poolUserFixture->status_long,
                    'status_short' => $poolUserFixture->status_short,
                    'home_team_id' => $poolUserFixture->home_team_id,
                    'away_team_id' => $poolUserFixture->away_team_id,
                    'home_goals' => $poolUserFixture->home_goals,
                    'away_goals' => $poolUserFixture->away_goals,
                    'finished_at' => $poolUserFixture->finished_at?->toIso8601String(),
                    'entry_order' => $poolUserFixture->entry_order,
                    'is_locked' => $poolUserFixture->is_locked,
                ];
            })
            ->all();
    }

    private function resolveAvatarUrl(?string $avatarPath): ?string
    {
        $avatarPath = !empty($avatarPath) ? $avatarPath : 'system/default01.png';
        $avatarUrl = null;

        if (!empty($avatarPath)) {
            $folderConfig = config('filesystems.folders.user_avatars', []);
            $disk = $folderConfig['driver'] ?? config('filesystems.default', 'local');
            $root = trim((string) ($folderConfig['root'] ?? 'users/avatars/'), '/');
            $storagePath = $root . '/' . ltrim($avatarPath, '/');

            if ($disk === 's3') {
                try {
                    $signedUrlTtlSeconds = (int) config('filesystems.disks.s3.signed_url_ttl_seconds', 7200);
                    $avatarUrl = Storage::disk('s3')->temporaryUrl(
                        $storagePath,
                        now()->addSeconds($signedUrlTtlSeconds)
                    );
                } catch (\Throwable $e) {
                    $configuredUrl = (string) config('filesystems.disks.s3.url', '');
                    $bucket = (string) config('filesystems.disks.s3.bucket', '');
                    $region = (string) config('filesystems.disks.s3.region', 'us-east-1');

                    $baseUrl = !empty($configuredUrl)
                        ? rtrim($configuredUrl, '/')
                        : sprintf('https://%s.s3.%s.amazonaws.com', $bucket, $region);

                    $avatarUrl = $baseUrl . '/' . ltrim($storagePath, '/');
                }
            } else {
                $avatarUrl = Storage::disk($disk)->url($storagePath);
            }

            if (!Str::startsWith((string) $avatarUrl, ['http://', 'https://'])) {
                $avatarUrl = rtrim((string) config('app.url', ''), '/') . '/' . ltrim((string) $avatarUrl, '/');
            }
        }

        return $avatarUrl;
    }

    private function mapFixtureStatus(string $status): string
    {
        if (in_array($status, self::LIVE_STATUS_SHORTS, true)) {
            return 'live';
        }

        if (in_array($status, self::UPCOMING_STATUS_SHORTS, true)) {
            return 'upcoming';
        }

        return 'finished';
    }
}
