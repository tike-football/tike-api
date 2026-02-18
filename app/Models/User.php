<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable implements OAuthenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'last_name',
        'email',
        'country_code',
        'phone_number',
        'full_phone_number',
        'password',
        'role',
        'avatar_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getRoleScopes(): array
    {
        return config('roles.' . $this->role . '.scopes') ?? [];
    }

    /**
     * Get the settings for the user.
     */
    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class);
    }

    /**
     * Get a specific setting value for the user.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getSetting(string $key, $default = null)
    {
        $setting = $this->settings()->where('key', $key)->first();
        
        if ($setting) {
            return $setting->value;
        }

        // Return default from config if exists
        return config("settings.{$key}.default", $default);
    }

    /**
     * Set a setting value for the user.
     *
     * @param string $key
     * @param mixed $value
     * @return Setting
     */
    public function setSetting(string $key, $value): Setting
    {
        return $this->settings()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }
}
