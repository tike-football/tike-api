<?php

namespace App\Http\Resources\Api\V1\Pool;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PoolResponse extends JsonResource
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
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'owner_id' => $this->owner_id,
            'league_id' => $this->league_id,
            'league_season_id' => $this->league_season_id,
            'group_id' => $this->group_id,
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
        ];

        $isDraft = (string) $this->status === 'draft';

        if ($isDraft && $this->group !== null) {
            $users = $this->group->users
                ->filter(fn ($user) => (bool) ($user->pivot?->is_accepted ?? false));
        } else {
            $users = $this->poolUsers
                ->map(fn ($poolUser) => $poolUser->user)
                ->filter();
        }

        $data['users'] = $users
            ->sortBy([
                ['name', 'asc'],
                ['last_name', 'asc'],
            ])
            ->values()
            ->map(fn ($user) => $this->transformGroupUser($user))
            ->all();

        if ((string) $this->scope === 'match') {
            $poolFixture = $this->poolFixtures->first();
            $fixture = $poolFixture?->fixture;
            $configuredPossibleScores = config('pools.possible_scores', []);
            $possibleScoreIds = $isDraft ? array_keys($configuredPossibleScores) : ($poolFixture?->possible_scores);

            $filteredPossibleScores = $configuredPossibleScores;

            if (!$isDraft && is_array($possibleScoreIds) && $possibleScoreIds !== []) {
                $filteredPossibleScores = array_intersect_key(
                    $configuredPossibleScores,
                    array_flip($possibleScoreIds)
                );
            }

            $data['match'] = $fixture !== null ? $this->transformFixture($fixture) : null;
            $data['possible_scores'] = $filteredPossibleScores;
            $data['possible_score_ids'] = $possibleScoreIds;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function transformFixture(mixed $fixture): array
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
    private function transformGroupUser(mixed $user): array
    {
        $avatarPath = !empty($user->avatar_path) ? $user->avatar_path : 'system/default01.png';
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

                    if (!empty($configuredUrl)) {
                        $baseUrl = rtrim($configuredUrl, '/');
                    } else {
                        $baseUrl = sprintf('https://%s.s3.%s.amazonaws.com', $bucket, $region);
                    }

                    $avatarUrl = $baseUrl . '/' . ltrim($storagePath, '/');
                }
            } else {
                $avatarUrl = Storage::disk($disk)->url($storagePath);
            }

            if (!Str::startsWith((string) $avatarUrl, ['http://', 'https://'])) {
                $avatarUrl = rtrim((string) config('app.url', ''), '/') . '/' . ltrim((string) $avatarUrl, '/');
            }
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'avatar_url' => $avatarUrl,
        ];
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
