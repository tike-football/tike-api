<?php

namespace App\Events\FootballData;

use App\Models\League;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class LeagueTeamsSynced
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public League $league;
    public int $season;

    /**
     * Create a new event instance.
     */
    public function __construct(League $league, int $season)
    {
        $this->league = $league;
        $this->season = $season;

        Log::info('LeagueTeamsSynced event triggered', [
            'league_id' => $league->id,
            'provider' => $league->provider,
            'provider_league_id' => $league->provider_league_id,
            'season' => $season,
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
