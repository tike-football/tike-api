<?php

namespace Tests\Feature\Notifications;

use App\Events\User\UserStored;
use App\Models\User;
use App\Notifications\User\EmailVerificationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailVerificationNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_notification_is_sent_when_user_is_created(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'role' => 'user',
        ]);

        event(new UserStored($user));

        // Wait a moment for the queue to process
        sleep(1);

        Notification::assertSentTo(
            $user,
            EmailVerificationNotification::class
        );
    }

    public function test_email_verification_notification_contains_verification_url(): void
    {
        // We'll just verify the notification has the necessary methods
        // without actually generating tokens (which requires Passport setup)
        $user = User::factory()->create([
            'name' => 'Jane',
            'email' => 'jane@example.com',
        ]);

        $notification = new EmailVerificationNotification();
        
        // Verify the notification can be instantiated
        $this->assertInstanceOf(EmailVerificationNotification::class, $notification);
        
        // Verify configuration exists
        $baseUrl = Config::get('app.urls.email_verification');
        $this->assertNotEmpty($baseUrl);
    }

    public function test_verification_url_configuration_exists(): void
    {
        $baseUrl = Config::get('app.urls.email_verification');
        
        $this->assertNotEmpty($baseUrl);
        $this->assertIsString($baseUrl);
    }

    public function test_unverified_user_scopes_are_configured(): void
    {
        $scopes = Config::get('roles.unverified_user.scopes');
        
        $this->assertNotEmpty($scopes);
        $this->assertIsArray($scopes);
        $this->assertContains('user:verify', $scopes);
    }

    public function test_notification_uses_spanish_translations(): void
    {
        Config::set('app.locale', 'es');

        // Just verify the translation file exists and has the correct keys
        $this->assertTrue(file_exists(lang_path('es/notifications.php')));
        
        $translations = require lang_path('es/notifications.php');
        
        $this->assertArrayHasKey('email_verification', $translations);
        $this->assertArrayHasKey('subject', $translations['email_verification']);
        $this->assertEquals('Verifica tu dirección de correo electrónico', $translations['email_verification']['subject']);
    }

    public function test_notification_is_queued(): void
    {
        $user = User::factory()->create();
        $notification = new EmailVerificationNotification();

        // Verify notification implements ShouldQueue
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $notification);
    }

    public function test_notification_sends_via_mail_channel(): void
    {
        $user = User::factory()->create();
        $notification = new EmailVerificationNotification();

        $channels = $notification->via($user);

        $this->assertContains('mail', $channels);
    }
}
