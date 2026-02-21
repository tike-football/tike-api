<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fixture extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'provider',
        'provider_fixture_id',
        'league_id',
        'season',
        'round',
        'referee',
        'timezone',
        'fixture_date',
        'timestamp',
        'venue_provider_id',
        'venue_name',
        'venue_city',
        'status_long',
        'status_short',
        'status_elapsed',
        'home_team_id',
        'away_team_id',
        'home_goals',
        'away_goals',
        'is_active',
        'external_payload',
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
            'fixture_date' => 'datetime',
            'is_active' => 'boolean',
            'external_payload' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function teamStats(): HasMany
    {
        return $this->hasMany(FixtureTeamStat::class);
    }
}
