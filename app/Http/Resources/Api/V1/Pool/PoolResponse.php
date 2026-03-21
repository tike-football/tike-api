<?php

namespace App\Http\Resources\Api\V1\Pool;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PoolResponse extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
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
            'is_active' => $this->is_active,
        ];
    }
}
