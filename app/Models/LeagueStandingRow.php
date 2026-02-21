<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueStandingRow extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'standing_id',
        'team_id',
        'rank_position',
        'points',
        'goals_diff',
        'matches_played',
        'matches_win',
        'matches_draw',
        'matches_lose',
        'goals_for',
        'goals_against',
        'row_form',
        'status',
        'row_description',
        'home_played',
        'home_win',
        'home_draw',
        'home_lose',
        'home_goals_for',
        'home_goals_against',
        'away_played',
        'away_win',
        'away_draw',
        'away_lose',
        'away_goals_for',
        'away_goals_against',
        'raw_row_payload',
        'last_synced_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'raw_row_payload' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function standing(): BelongsTo
    {
        return $this->belongsTo(LeagueStanding::class, 'standing_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
