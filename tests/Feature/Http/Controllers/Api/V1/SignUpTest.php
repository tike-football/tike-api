<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SignUpTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_with_valid_data(): void
    {
        $userData = [
            'name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ];

        $response = $this->postJson('/api/v1/auth/sign-up', $userData);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'User registered successfully.',
            ])
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'name',
                    'last_name',
                    'email',
                    'role',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'role' => 'user',
        ]);
    }

    public function test_registration_requires_name(): void
    {
        $userData = [
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ];

        $response = $this->postJson('/api/v1/auth/sign-up', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_registration_requires_last_name(): void
    {
        $userData = [
            'name' => 'John',
            'email' => 'john.doe@example.com',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ];

        $response = $this->postJson('/api/v1/auth/sign-up', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['last_name']);
    }

    public function test_registration_requires_email(): void
    {
        $userData = [
            'name' => 'John',
            'last_name' => 'Doe',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ];

        $response = $this->postJson('/api/v1/auth/sign-up', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_requires_valid_email(): void
    {
        $userData = [
            'name' => 'John',
            'last_name' => 'Doe',
            'email' => 'invalid-email',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ];

        $response = $this->postJson('/api/v1/auth/sign-up', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_requires_unique_email(): void
    {
        User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $userData = [
            'name' => 'John',
            'last_name' => 'Doe',
            'email' => 'existing@example.com',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ];

        $response = $this->postJson('/api/v1/auth/sign-up', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email'])
            ->assertJson([
                'message' => 'Validation failed.',
                'errors' => [
                    'email' => ['This email is already registered.']
                ]
            ]);
    }

    public function test_registration_requires_password(): void
    {
        $userData = [
            'name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
        ];

        $response = $this->postJson('/api/v1/auth/sign-up', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_registration_requires_password_confirmation(): void
    {
        $userData = [
            'name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'SecurePass123',
        ];

        $response = $this->postJson('/api/v1/auth/sign-up', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_registration_requires_matching_passwords(): void
    {
        $userData = [
            'name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'SecurePass123',
            'password_confirmation' => 'DifferentPass123',
        ];

        $response = $this->postJson('/api/v1/auth/sign-up', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password'])
            ->assertJson([
                'message' => 'Validation failed.',
                'errors' => [
                    'password' => ['The password confirmation does not match.']
                ]
            ]);
    }

    public function test_registration_requires_medium_strength_password(): void
    {
        // Password without uppercase
        $userData = [
            'name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'weakpass123',
            'password_confirmation' => 'weakpass123',
        ];

        $response = $this->postJson('/api/v1/auth/sign-up', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        // Password without numbers
        $userData['password'] = 'WeakPassword';
        $userData['password_confirmation'] = 'WeakPassword';

        $response = $this->postJson('/api/v1/auth/sign-up', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        // Password too short
        $userData['password'] = 'Short1';
        $userData['password_confirmation'] = 'Short1';

        $response = $this->postJson('/api/v1/auth/sign-up', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_registered_user_has_default_user_role(): void
    {
        $userData = [
            'name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ];

        $this->postJson('/api/v1/auth/sign-up', $userData);

        $user = User::where('email', 'john.doe@example.com')->first();

        $this->assertNotNull($user);
        $this->assertEquals('user', $user->role);
    }

    public function test_password_is_hashed_in_database(): void
    {
        $userData = [
            'name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ];

        $this->postJson('/api/v1/auth/sign-up', $userData);

        $user = User::where('email', 'john.doe@example.com')->first();

        $this->assertNotNull($user);
        $this->assertNotEquals('SecurePass123', $user->password);
        $this->assertTrue(Hash::check('SecurePass123', $user->password));
    }
}
