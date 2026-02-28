<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class League extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'provider',
        'provider_league_id',
        'name',
        'type',
        'country_name',
        'country_code',
        'logo',
        'flag',
        'current',
        'external_payload',
        'last_synced_at',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current' => 'boolean',
            'external_payload' => 'array',
            'last_synced_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function seasons(): HasMany
    {
        return $this->hasMany(LeagueSeason::class);
    }

    public function currentSeason(): HasOne
    {
        return $this->hasOne(LeagueSeason::class)
            ->where('current', true)
            ->latestOfMany('year');
    }

    public function currentSeasonYear(): ?int
    {
        $season = $this->currentSeason;

        return $season !== null ? (int) $season->year : null;
    }
}
