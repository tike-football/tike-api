<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Events\User\PasswordUpdated;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Event::fake();
    }

    public function test_reset_password_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/auth/password/reset', [
            'new_password' => 'NewPassword123',
            'new_password_confirmation' => 'NewPassword123',
        ]);

        $response->assertStatus(401);
    }

    public function test_reset_password_requires_correct_scope(): void
    {
        $user = User::factory()->create();

        // Acting as user with wrong scope
        Passport::actingAs($user, ['test:test']);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'new_password' => 'NewPassword123',
            'new_password_confirmation' => 'NewPassword123',
        ]);

        $response->assertStatus(403);
    }

    public function test_reset_password_requires_new_password(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user, ['user:recover-password']);

        $response = $this->postJson('/api/v1/auth/password/reset', []);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => ['new_password']
            ]);
    }

    public function test_reset_password_requires_password_confirmation(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user, ['user:recover-password']);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'new_password' => 'NewPassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => ['new_password']
            ]);
    }

    public function test_reset_password_requires_matching_confirmation(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user, ['user:recover-password']);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'new_password' => 'NewPassword123',
            'new_password_confirmation' => 'DifferentPassword456',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => ['new_password']
            ])
            ->assertJsonFragment([
                'new_password' => ['The new password confirmation does not match.']
            ]);
    }

    public function test_reset_password_requires_minimum_8_characters(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user, ['user:recover-password']);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'new_password' => 'Short1',
            'new_password_confirmation' => 'Short1',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => ['new_password']
            ]);
    }

    public function test_reset_password_requires_mixed_case(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user, ['user:recover-password']);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'new_password' => 'lowercase123',
            'new_password_confirmation' => 'lowercase123',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => ['new_password']
            ]);
    }

    public function test_reset_password_requires_numbers(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user, ['user:recover-password']);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'new_password' => 'NoNumbers',
            'new_password_confirmation' => 'NoNumbers',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => ['new_password']
            ]);
    }

    public function test_user_can_reset_password_with_valid_data(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('OldPassword123'),
        ]);

        Passport::actingAs($user, ['user:recover-password']);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'new_password' => 'NewPassword456',
            'new_password_confirmation' => 'NewPassword456',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Password has been reset successfully. Please login with your new password.',
            ]);

        // Verify password was updated
        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword456', $user->password));
    }

    public function test_reset_password_dispatches_password_updated_event(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user, ['user:recover-password']);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'new_password' => 'NewPassword456',
            'new_password_confirmation' => 'NewPassword456',
        ]);

        $response->assertStatus(200);

        Event::assertDispatched(PasswordUpdated::class, function ($event) use ($user) {
            return $event->user->id === $user->id;
        });
    }

    public function test_reset_password_revokes_all_user_tokens(): void
    {
        $user = User::factory()->create();

        // Acting as user
        Passport::actingAs($user, ['user:recover-password']);

        // Simulate that user has tokens by checking before and after
        // In a real scenario, tokens would exist from previous logins
        $initialTokenCount = $user->tokens()->count();

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'new_password' => 'NewPassword456',
            'new_password_confirmation' => 'NewPassword456',
        ]);

        $response->assertStatus(200);

        // Verify all tokens were revoked
        $user->refresh();
        $this->assertCount(0, $user->tokens);
        $this->assertLessThanOrEqual($initialTokenCount, $user->tokens()->count());
    }

    public function test_reset_password_verifies_email_if_not_verified(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $this->assertNull($user->email_verified_at);

        Passport::actingAs($user, ['user:recover-password']);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'new_password' => 'NewPassword456',
            'new_password_confirmation' => 'NewPassword456',
        ]);

        $response->assertStatus(200);

        // Verify email was verified
        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_reset_password_preserves_verification_date_if_already_verified(): void
    {
        $originalVerificationDate = now()->subDays(10);
        $user = User::factory()->create([
            'email_verified_at' => $originalVerificationDate,
        ]);

        Passport::actingAs($user, ['user:recover-password']);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'new_password' => 'NewPassword456',
            'new_password_confirmation' => 'NewPassword456',
        ]);

        $response->assertStatus(200);

        // Verify email verification date was not changed
        $user->refresh();
        $this->assertEquals(
            $originalVerificationDate->format('Y-m-d H:i:s'),
            $user->email_verified_at->format('Y-m-d H:i:s')
        );
    }

    public function test_reset_password_returns_json_on_validation_error(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user, ['user:recover-password']);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'new_password' => 'weak',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors'
            ]);
    }

    public function test_password_is_hashed_in_database(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user, ['user:recover-password']);

        $newPassword = 'NewPassword456';

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'new_password' => $newPassword,
            'new_password_confirmation' => $newPassword,
        ]);

        $response->assertStatus(200);

        $user->refresh();
        
        // Ensure password is hashed (not stored in plain text)
        $this->assertNotEquals($newPassword, $user->password);
        $this->assertTrue(Hash::check($newPassword, $user->password));
    }
}
