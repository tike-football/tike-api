<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'code_alpha3',
        'name',
        'native_name',
        'phone_code',
        'flag_emoji',
        'currency_code',
        'region',
    ];

    /**
     * Get users from this country.
     */
    public function users()
    {
        return $this->hasMany(User::class, 'country_code', 'code');
    }
}
