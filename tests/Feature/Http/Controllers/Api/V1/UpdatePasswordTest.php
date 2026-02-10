<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\Traits\WithApiKey;

class UpdatePasswordTest extends TestCase
{
    use RefreshDatabase, WithApiKey;

    public function test_user_can_update_password_with_valid_data(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('OldPassword123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        Passport::actingAs($user, ['user:update-password']);

        $response = $this->patchJsonWithApiKey('/api/v1/auth/password', [
            'current_password' => 'OldPassword123',
            'new_password' => 'NewSecurePass456',
            'new_password_confirmation' => 'NewSecurePass456',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Password updated successfully.',
            ]);

        // Verify the password was actually changed
        $user->refresh();
        $this->assertTrue(Hash::check('NewSecurePass456', $user->password));
        $this->assertFalse(Hash::check('OldPassword123', $user->password));
    }

    public function test_update_password_requires_authentication(): void
    {
        $response = $this->patchJsonWithApiKey('/api/v1/auth/password', [
            'current_password' => 'OldPassword123',
            'new_password' => 'NewSecurePass456',
            'new_password_confirmation' => 'NewSecurePass456',
        ]);

        $response->assertStatus(401);
    }

    public function test_update_password_requires_correct_scope(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('OldPassword123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        // Acting with wrong scope
        Passport::actingAs($user, ['user:verify']);

        $response = $this->patchJsonWithApiKey('/api/v1/auth/password', [
            'current_password' => 'OldPassword123',
            'new_password' => 'NewSecurePass456',
            'new_password_confirmation' => 'NewSecurePass456',
        ]);

        $response->assertStatus(403);
    }

    public function test_update_password_requires_current_password(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('OldPassword123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        Passport::actingAs($user, ['user:update-password']);

        $response = $this->patchJsonWithApiKey('/api/v1/auth/password', [
            'new_password' => 'NewSecurePass456',
            'new_password_confirmation' => 'NewSecurePass456',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_update_password_validates_current_password_is_correct(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('OldPassword123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        Passport::actingAs($user, ['user:update-password']);

        $response = $this->patchJsonWithApiKey('/api/v1/auth/password', [
            'current_password' => 'WrongPassword123',
            'new_password' => 'NewSecurePass456',
            'new_password_confirmation' => 'NewSecurePass456',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_update_password_requires_new_password(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('OldPassword123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        Passport::actingAs($user, ['user:update-password']);

        $response = $this->patchJsonWithApiKey('/api/v1/auth/password', [
            'current_password' => 'OldPassword123',
            'new_password_confirmation' => 'NewSecurePass456',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);
    }

    public function test_update_password_requires_password_confirmation(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('OldPassword123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        Passport::actingAs($user, ['user:update-password']);

        $response = $this->patchJsonWithApiKey('/api/v1/auth/password', [
            'current_password' => 'OldPassword123',
            'new_password' => 'NewSecurePass456',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);
    }

    public function test_update_password_requires_matching_confirmation(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('OldPassword123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        Passport::actingAs($user, ['user:update-password']);

        $response = $this->patchJsonWithApiKey('/api/v1/auth/password', [
            'current_password' => 'OldPassword123',
            'new_password' => 'NewSecurePass456',
            'new_password_confirmation' => 'DifferentPassword789',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);
    }

    public function test_update_password_requires_medium_strength_password(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('OldPassword123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        Passport::actingAs($user, ['user:update-password']);

        $response = $this->patchJsonWithApiKey('/api/v1/auth/password', [
            'current_password' => 'OldPassword123',
            'new_password' => 'weak',
            'new_password_confirmation' => 'weak',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);
    }

    public function test_admin_can_update_password(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('OldAdminPass123'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        Passport::actingAs($admin, ['user:update-password']);

        $response = $this->patchJsonWithApiKey('/api/v1/auth/password', [
            'current_password' => 'OldAdminPass123',
            'new_password' => 'NewAdminPass456',
            'new_password_confirmation' => 'NewAdminPass456',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Password updated successfully.',
            ]);

        // Verify the password was actually changed
        $admin->refresh();
        $this->assertTrue(Hash::check('NewAdminPass456', $admin->password));
    }

    public function test_password_is_hashed_in_database(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('OldPassword123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);

        Passport::actingAs($user, ['user:update-password']);

        $newPassword = 'NewSecurePass456';

        $this->patchJsonWithApiKey('/api/v1/auth/password', [
            'current_password' => 'OldPassword123',
            'new_password' => $newPassword,
            'new_password_confirmation' => $newPassword,
        ]);

        $user->refresh();

        // Password should not be stored in plain text
        $this->assertNotEquals($newPassword, $user->password);
        // But Hash::check should work
        $this->assertTrue(Hash::check($newPassword, $user->password));
    }
}
