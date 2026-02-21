<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Team extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'provider',
        'provider_team_id',
        'league_id',
        'season',
        'name',
        'code',
        'country_name',
        'founded',
        'national',
        'logo',
        'venue_provider_id',
        'venue_name',
        'venue_address',
        'venue_city',
        'venue_capacity',
        'venue_surface',
        'venue_image',
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
            'national' => 'boolean',
            'is_active' => 'boolean',
            'external_payload' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function leagues(): BelongsToMany
    {
        return $this->belongsToMany(League::class, 'player_league_stats', 'team_id', 'league_id')
            ->withPivot(['season', 'provider'])
            ->withTimestamps()
            ->distinct();
    }
}
