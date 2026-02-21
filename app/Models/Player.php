<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'provider',
        'provider_player_id',
        'firstname',
        'lastname',
        'full_name',
        'age',
        'birth_date',
        'birth_place',
        'birth_country',
        'nationality',
        'height',
        'weight',
        'injured',
        'photo',
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
            'birth_date' => 'date',
            'injured' => 'boolean',
            'is_active' => 'boolean',
            'external_payload' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function teamSeasons(): HasMany
    {
        return $this->hasMany(TeamPlayerSeason::class);
    }

    public function leagueStats(): HasMany
    {
        return $this->hasMany(PlayerLeagueStat::class);
    }
}
