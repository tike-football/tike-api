<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixtureTeamStat extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'provider',
        'fixture_id',
        'team_id',
        'is_home',
        'winner',
        'goals',
        'raw_lineup',
        'raw_statistics',
        'raw_events',
        'raw_players',
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
            'is_home' => 'boolean',
            'winner' => 'boolean',
            'raw_lineup' => 'array',
            'raw_statistics' => 'array',
            'raw_events' => 'array',
            'raw_players' => 'array',
            'external_payload' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function fixture(): BelongsTo
    {
        return $this->belongsTo(Fixture::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
