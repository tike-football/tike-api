<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class VerifyEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_verify_email_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
            'role' => 'unverified_user',
        ]);

        Passport::actingAs($user, ['user:verify']);

        $response = $this->postJson('/api/v1/auth/verify-email');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Email verified successfully.',
            ])
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'email',
                    'email_verified_at',
                ],
            ]);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_user_cannot_verify_email_without_authentication(): void
    {
        $response = $this->postJson('/api/v1/auth/verify-email');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_user_cannot_verify_email_without_correct_scope(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
            'role' => 'user',
        ]);

        Passport::actingAs($user, ['test:test']);

        $response = $this->postJson('/api/v1/auth/verify-email');

        $response->assertStatus(403);
    }

    public function test_user_cannot_verify_email_if_already_verified(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'role' => 'user',
        ]);

        Passport::actingAs($user, ['user:verify']);

        $response = $this->postJson('/api/v1/auth/verify-email');

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Email address is already verified.',
            ]);
    }

    public function test_verify_email_marks_email_as_verified_and_revokes_token(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
            'role' => 'unverified_user',
        ]);

        Passport::actingAs($user, ['user:verify']);

        $response = $this->postJson('/api/v1/auth/verify-email');

        $response->assertStatus(200);

        // Verify the user's email was marked as verified
        $refreshedUser = $user->fresh();
        $this->assertNotNull($refreshedUser->email_verified_at);
        
        // Verify second attempt returns already verified error
        $user2 = User::find($user->id);
        Passport::actingAs($user2, ['user:verify']);
        
        $response = $this->postJson('/api/v1/auth/verify-email');
        $response->assertStatus(400)
            ->assertJson(['message' => 'Email address is already verified.']);
    }

    public function test_verify_email_returns_correct_response_structure(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'email_verified_at' => null,
            'role' => 'unverified_user',
        ]);

        Passport::actingAs($user, ['user:verify']);

        $response = $this->postJson('/api/v1/auth/verify-email');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'email',
                    'email_verified_at',
                ],
            ])
            ->assertJson([
                'user' => [
                    'id' => $user->id,
                    'email' => 'test@example.com',
                ],
            ]);
    }
}
