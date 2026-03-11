<?php

namespace App\Http\Resources\Api\V1\Friend;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FriendSearchResponse extends JsonResource
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
            'name' => $this->name,
            'last_name' => $this->last_name,
        ];
    }
}
