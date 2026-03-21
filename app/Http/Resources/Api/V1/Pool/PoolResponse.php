<?php

namespace App\Http\Resources\Api\V1\Pool;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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

        if ((string) $this->scope === 'match') {
            $poolFixture = $this->poolFixtures->first();
            $fixture = $poolFixture?->fixture;
            $data['match'] = $fixture !== null ? $this->transformFixture($fixture) : null;
            $data['possible_scores'] = config('pools.possible_scores', []);
            $data['possible_score_ids'] = $poolFixture?->possible_scores;
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
