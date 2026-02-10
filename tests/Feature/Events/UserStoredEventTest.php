<?php

namespace Tests\Feature\Events;

use App\Events\User\UserStored;
use App\Listeners\User\SendEmailVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\Traits\WithApiKey;

class UserStoredEventTest extends TestCase
{
    use RefreshDatabase, WithApiKey;

    public function test_user_stored_event_is_dispatched_on_registration(): void
    {
        Event::fake([UserStored::class]);

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

        $response->assertStatus(201);

        Event::assertDispatched(UserStored::class, function ($event) {
            return $event->user->email === 'john.doe@example.com';
        });
    }

    public function test_send_email_verification_listener_is_called(): void
    {
        Event::fake([UserStored::class]);

        $userData = [
            'name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane.smith@example.com',
            'country_code' => '+1',
            'phone_number' => '5551234568',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ];

        $this->postJsonWithApiKey('/api/v1/auth/sign-up', $userData);

        Event::assertDispatched(UserStored::class);
        Event::assertListening(
            UserStored::class,
            SendEmailVerification::class
        );
    }

    public function test_listener_is_queued_on_emails_queue(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        // Manually call the listener to test queue assignment
        $listener = new SendEmailVerification();
        
        $this->assertEquals('emails', $listener->queue);
    }
}
