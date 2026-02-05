<?php

namespace Tests\Unit\Models;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_setting_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $setting = Setting::factory()->create([
            'user_id' => $user->id,
            'key' => 'language',
            'value' => 'es',
        ]);

        $this->assertInstanceOf(User::class, $setting->user);
        $this->assertEquals($user->id, $setting->user->id);
    }

    public function test_user_has_many_settings(): void
    {
        $user = User::factory()->create();
        
        Setting::factory()->create([
            'user_id' => $user->id,
            'key' => 'language',
            'value' => 'es',
        ]);

        Setting::factory()->create([
            'user_id' => $user->id,
            'key' => 'theme',
            'value' => 'dark',
        ]);

        $this->assertCount(2, $user->settings);
    }

    public function test_user_can_get_setting(): void
    {
        $user = User::factory()->create();
        
        Setting::factory()->create([
            'user_id' => $user->id,
            'key' => 'language',
            'value' => 'en',
        ]);

        $this->assertEquals('en', $user->getSetting('language'));
    }

    public function test_user_get_setting_returns_default_if_not_set(): void
    {
        $user = User::factory()->create();

        // Should return default from config
        $this->assertEquals('es', $user->getSetting('language'));
    }

    public function test_user_get_setting_returns_custom_default(): void
    {
        $user = User::factory()->create();

        $this->assertEquals('custom', $user->getSetting('non_existent_key', 'custom'));
    }

    public function test_user_can_set_setting(): void
    {
        $user = User::factory()->create();

        $setting = $user->setSetting('language', 'en');

        $this->assertInstanceOf(Setting::class, $setting);
        $this->assertEquals('language', $setting->key);
        $this->assertEquals('en', $setting->value);
        $this->assertEquals($user->id, $setting->user_id);
    }

    public function test_user_set_setting_updates_existing(): void
    {
        $user = User::factory()->create();

        $user->setSetting('language', 'es');
        $user->setSetting('language', 'en');

        $this->assertCount(1, $user->settings);
        $this->assertEquals('en', $user->getSetting('language'));
    }

    public function test_settings_are_deleted_when_user_is_deleted(): void
    {
        $user = User::factory()->create();
        
        $setting = Setting::factory()->create([
            'user_id' => $user->id,
        ]);

        $settingId = $setting->id;

        $user->delete();

        $this->assertDatabaseMissing('settings', ['id' => $settingId]);
    }

    public function test_user_cannot_have_duplicate_setting_keys(): void
    {
        $user = User::factory()->create();

        Setting::factory()->create([
            'user_id' => $user->id,
            'key' => 'language',
            'value' => 'es',
        ]);

        // Attempting to create duplicate should fail
        $this->expectException(\Illuminate\Database\QueryException::class);

        Setting::factory()->create([
            'user_id' => $user->id,
            'key' => 'language',
            'value' => 'en',
        ]);
    }
}
