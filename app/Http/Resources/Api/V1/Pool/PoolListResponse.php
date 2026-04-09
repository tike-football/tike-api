<?php

namespace App\Http\Resources\Api\V1\Pool;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PoolListResponse extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $authUser = $request->user();
        $isOwner = $authUser !== null && (int) $this->owner_id === (int) $authUser->id;
        $matchId = null;

        if ((string) $this->scope === 'match') {
            $matchId = $this->poolFixtures->first()?->fixture_id;
        }

        return [
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
            'group' => $this->group !== null ? [
                'id' => $this->group->id,
                'name' => $this->group->name,
            ] : null,
            'league' => $this->league !== null ? [
                'id' => $this->league->id,
                'name' => $this->league->name,
                'country' => $this->league->country_name,
                'season' => $this->leagueSeason?->year,
                'league_season_id' => $this->league_season_id,
            ] : null,
            'match_id' => $matchId,
            'is_owner' => $isOwner,
            'total_approved_users' => $this->approved_pool_users_count ?? 0,
            'total_pending_join_requests' => $isOwner ? ($this->pending_pool_users_count ?? 0) : null,
        ];
    }
}
