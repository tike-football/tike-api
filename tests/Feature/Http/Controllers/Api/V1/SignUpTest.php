<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Tests\Traits\WithApiKey;

class SignUpTest extends TestCase
{
    use RefreshDatabase, WithApiKey;

    /**
     * Get base user data for registration
     */
    private function getBaseUserData(): array
    {
        return [
            'name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'country_code' => '+1',
            'phone_number' => '5551234567',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ];
    }

    public function test_user_can_register_with_valid_data(): void
    {
        Notification::fake();

        $userData = [
            'name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'country_code' => '+1',
            'phone_number' => '5551234567',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ];

        $response = $this->postJsonWithApiKey('/api/v1/auth/sign-up', $userData);

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
                    'country_code',
                    'phone_number',
                    'role',
                    'language',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'country_code' => '+1',
            'phone_number' => '5551234567',
            'full_phone_number' => '+15551234567',
            'role' => 'user',
        ]);
    }

    public function test_registration_requires_name(): void
    {
        $userData = $this->getBaseUserData();
        unset($userData['name']);

        $response = $this->postJsonWithApiKey('/api/v1/auth/sign-up', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_registration_requires_last_name(): void
    {
        $userData = $this->getBaseUserData();
        unset($userData['last_name']);

        $response = $this->postJsonWithApiKey('/api/v1/auth/sign-up', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['last_name']);
    }

    public function test_registration_requires_email(): void
    {
        $userData = $this->getBaseUserData();
        unset($userData['email']);

        $response = $this->postJsonWithApiKey('/api/v1/auth/sign-up', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_requires_valid_email(): void
    {
        $userData = $this->getBaseUserData();
        $userData['email'] = 'invalid-email';

        $response = $this->postJsonWithApiKey('/api/v1/auth/sign-up', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_requires_unique_email(): void
    {
        User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $userData = $this->getBaseUserData();
        $userData['email'] = 'existing@example.com';

        $response = $this->postJsonWithApiKey('/api/v1/auth/sign-up', $userData);

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
        $userData = $this->getBaseUserData();
        unset($userData['password']);
        unset($userData['password_confirmation']);

        $response = $this->postJsonWithApiKey('/api/v1/auth/sign-up', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_registration_requires_password_confirmation(): void
    {
        $userData = $this->getBaseUserData();
        unset($userData['password_confirmation']);

        $response = $this->postJsonWithApiKey('/api/v1/auth/sign-up', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_registration_requires_matching_passwords(): void
    {
        $userData = $this->getBaseUserData();
        $userData['password_confirmation'] = 'DifferentPass123';

        $response = $this->postJsonWithApiKey('/api/v1/auth/sign-up', $userData);

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
        $userData = $this->getBaseUserData();
        $userData['password'] = 'weakpass123';
        $userData['password_confirmation'] = 'weakpass123';

        $response = $this->postJsonWithApiKey('/api/v1/auth/sign-up', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        // Password without numbers
        $userData = $this->getBaseUserData();
        $userData['password'] = 'WeakPassword';
        $userData['password_confirmation'] = 'WeakPassword';

        $response = $this->postJsonWithApiKey('/api/v1/auth/sign-up', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        // Password too short
        $userData = $this->getBaseUserData();
        $userData['password'] = 'Short1';
        $userData['password_confirmation'] = 'Short1';

        $response = $this->postJsonWithApiKey('/api/v1/auth/sign-up', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_registered_user_has_default_user_role(): void
    {
        Notification::fake();

        $userData = $this->getBaseUserData();

        $this->postJsonWithApiKey('/api/v1/auth/sign-up', $userData);

        $user = User::where('email', 'john.doe@example.com')->first();

        $this->assertNotNull($user);
        $this->assertEquals('user', $user->role);
    }

    public function test_password_is_hashed_in_database(): void
    {
        Notification::fake();

        $userData = $this->getBaseUserData();

        $this->postJsonWithApiKey('/api/v1/auth/sign-up', $userData);

        $user = User::where('email', 'john.doe@example.com')->first();

        $this->assertNotNull($user);
        $this->assertNotEquals('SecurePass123', $user->password);
        $this->assertTrue(Hash::check('SecurePass123', $user->password));
    }

    public function test_user_can_register_with_language(): void
    {
        Notification::fake();

        $userData = $this->getBaseUserData();
        $userData['language'] = 'en';

        $response = $this->postJsonWithApiKey('/api/v1/auth/sign-up', $userData);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'User registered successfully.',
                'user' => [
                    'language' => 'en',
                ],
            ]);

        $user = User::where('email', 'john.doe@example.com')->first();
        $this->assertEquals('en', $user->getSetting('language'));
    }

    public function test_user_registers_with_default_language_if_not_provided(): void
    {
        Notification::fake();

        $userData = $this->getBaseUserData();

        $response = $this->postJsonWithApiKey('/api/v1/auth/sign-up', $userData);

        $response->assertStatus(201);

        $user = User::where('email', 'john.doe@example.com')->first();
        $this->assertEquals('es', $user->getSetting('language')); // Default is 'es'
    }

    public function test_registration_uses_default_language_if_invalid_provided(): void
    {
        Notification::fake();

        $userData = $this->getBaseUserData();
        $userData['language'] = 'fr'; // Valid format but not in options

        $response = $this->postJsonWithApiKey('/api/v1/auth/sign-up', $userData);

        $response->assertStatus(201); // Should succeed

        $user = User::where('email', 'john.doe@example.com')->first();
        $this->assertEquals('es', $user->getSetting('language')); // Uses default
    }

    public function test_registration_validates_language_length(): void
    {
        Notification::fake();

        $userData = $this->getBaseUserData();
        $userData['language'] = 'eng'; // 3 characters, should fail

        $response = $this->postJsonWithApiKey('/api/v1/auth/sign-up', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['language']);
    }

    public function test_registration_requires_country_code(): void
    {
        $userData = [
            'name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone_number' => '5551234567',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ];

        $response = $this->postJsonWithApiKey('/api/v1/auth/sign-up', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['country_code']);
    }

    public function test_registration_requires_phone_number(): void
    {
        $userData = [
            'name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'country_code' => '+1',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ];

        $response = $this->postJsonWithApiKey('/api/v1/auth/sign-up', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone_number']);
    }

    public function test_registration_requires_unique_phone_number(): void
    {
        // Create user with specific phone number
        User::factory()->create([
            'country_code' => '+52',
            'phone_number' => '5551234567',
            'full_phone_number' => '+525551234567',
        ]);

        $userData = [
            'name' => 'John',
            'last_name' => 'Doe',
            'email' => 'different@example.com',
            'country_code' => '+52',
            'phone_number' => '5551234567',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ];

        $response = $this->postJsonWithApiKey('/api/v1/auth/sign-up', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone_number'])
            ->assertJson([
                'message' => 'Validation failed.',
                'errors' => [
                    'phone_number' => ['This phone number is already registered.']
                ]
            ]);
    }

    public function test_registration_allows_same_phone_number_with_different_country_code(): void
    {
        Notification::fake();

        // Create user with phone number in one country
        User::factory()->create([
            'email' => 'first@example.com',
            'country_code' => '+1',
            'phone_number' => '5551234567',
            'full_phone_number' => '+15551234567',
        ]);

        // Try to register with same phone number but different country code
        $userData = [
            'name' => 'John',
            'last_name' => 'Doe',
            'email' => 'second@example.com',
            'country_code' => '+52',
            'phone_number' => '5551234567',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ];

        $response = $this->postJsonWithApiKey('/api/v1/auth/sign-up', $userData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'second@example.com',
            'country_code' => '+52',
            'phone_number' => '5551234567',
            'full_phone_number' => '+525551234567',
        ]);
    }

    public function test_full_phone_number_is_generated_correctly(): void
    {
        Notification::fake();

        $userData = [
            'name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'country_code' => '+34',
            'phone_number' => '612345678',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ];

        $response = $this->postJsonWithApiKey('/api/v1/auth/sign-up', $userData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'john.doe@example.com',
            'country_code' => '+34',
            'phone_number' => '612345678',
            'full_phone_number' => '+34612345678',
        ]);
    }
}
