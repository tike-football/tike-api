<?php

namespace App\Events\FootballData;

use App\Models\League;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class LeagueSynced
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public League $league;

    /**
     * Create a new event instance.
     */
    public function __construct(League $league)
    {
        $this->league = $league;

        Log::info('LeagueSynced event triggered', [
            'league_id' => $league->id,
            'provider' => $league->provider,
            'provider_league_id' => $league->provider_league_id,
        ]);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
