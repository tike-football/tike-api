<?php

namespace App\Events\FootballData;

use App\Models\League;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FixtureFinished
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public League $league;
    public int $season;
    public int $providerFixtureId;

    public function __construct(League $league, int $season, int $providerFixtureId)
    {
        $this->league = $league;
        $this->season = $season;
        $this->providerFixtureId = $providerFixtureId;

        Log::info('FixtureFinished event triggered', [
            'league_id' => $league->id,
            'provider_league_id' => $league->provider_league_id,
            'season' => $season,
            'provider_fixture_id' => $providerFixtureId,
        ]);
    }

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
