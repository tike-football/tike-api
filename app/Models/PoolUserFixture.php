<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoolUserFixture extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'pool_id',
        'user_id',
        'fixture_id',
        'league_id',
        'season',
        'round',
        'timezone',
        'fixture_date',
        'timestamp',
        'status_long',
        'status_short',
        'home_team_id',
        'away_team_id',
        'home_goals',
        'away_goals',
        'finished_at',
        'entry_order',
        'is_locked',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'season' => 'integer',
            'timestamp' => 'integer',
            'fixture_date' => 'datetime',
            'home_goals' => 'integer',
            'away_goals' => 'integer',
            'entry_order' => 'integer',
            'is_locked' => 'boolean',
            'finished_at' => 'datetime',
        ];
    }

    public function pool(): BelongsTo
    {
        return $this->belongsTo(Pool::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fixture(): BelongsTo
    {
        return $this->belongsTo(Fixture::class);
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
}
