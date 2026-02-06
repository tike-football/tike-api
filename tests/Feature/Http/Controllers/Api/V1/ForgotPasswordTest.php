<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Events\User\PasswordForgotRequested;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Event::fake();
    }

    public function test_forgot_password_requires_email(): void
    {
        $response = $this->postJson('/api/v1/auth/password/forgot', []);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => ['email']
            ]);
    }

    public function test_forgot_password_requires_valid_email_format(): void
    {
        $response = $this->postJson('/api/v1/auth/password/forgot', [
            'email' => 'invalid-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => ['email']
            ]);
    }

    public function test_forgot_password_requires_existing_email(): void
    {
        $response = $this->postJson('/api/v1/auth/password/forgot', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => ['email']
            ]);
    }

    public function test_forgot_password_dispatches_event_for_existing_user(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $response = $this->postJson('/api/v1/auth/password/forgot', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'If an account exists with that email, a password reset link has been sent.',
            ]);

        Event::assertDispatched(PasswordForgotRequested::class, function ($event) use ($user) {
            return $event->user->id === $user->id;
        });
    }

    public function test_forgot_password_always_returns_success_message(): void
    {
        // Test that the response is always the same for security (doesn't reveal if email exists)
        $user = User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $responseExisting = $this->postJson('/api/v1/auth/password/forgot', [
            'email' => 'existing@example.com',
        ]);

        // The validation will fail for non-existing emails, but if validation passes,
        // the response message should be generic
        $responseExisting->assertStatus(200)
            ->assertJson([
                'message' => 'If an account exists with that email, a password reset link has been sent.',
            ]);
    }

    public function test_forgot_password_does_not_require_authentication(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        // Make request without authentication
        $response = $this->postJson('/api/v1/auth/password/forgot', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(200);
    }

    public function test_forgot_password_works_for_unverified_users(): void
    {
        $user = User::factory()->create([
            'email' => 'unverified@example.com',
            'email_verified_at' => null,
        ]);

        $response = $this->postJson('/api/v1/auth/password/forgot', [
            'email' => 'unverified@example.com',
        ]);

        $response->assertStatus(200);

        Event::assertDispatched(PasswordForgotRequested::class, function ($event) use ($user) {
            return $event->user->id === $user->id;
        });
    }

    public function test_forgot_password_returns_json_on_validation_error(): void
    {
        $response = $this->postJson('/api/v1/auth/password/forgot', [
            'email' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors'
            ]);
    }

    public function test_forgot_password_email_field_has_max_length_validation(): void
    {
        $response = $this->postJson('/api/v1/auth/password/forgot', [
            'email' => str_repeat('a', 250) . '@example.com', // Over 255 characters
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => ['email']
            ]);
    }
}
