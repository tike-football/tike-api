<?php

namespace App\Notifications\User;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;

class EmailVerificationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        $this->onQueue('emails');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $verificationUrl = $this->generateVerificationUrl($notifiable);

        return (new MailMessage)
            ->subject(Lang::get('notifications.email_verification.subject'))
            ->greeting(Lang::get('notifications.email_verification.greeting', ['name' => $notifiable->name]))
            ->line(Lang::get('notifications.email_verification.line1'))
            ->line(Lang::get('notifications.email_verification.line2'))
            ->action(Lang::get('notifications.email_verification.action'), $verificationUrl)
            ->line(Lang::get('notifications.email_verification.line3'))
            ->line(Lang::get('notifications.email_verification.line4'))
            ->line(Lang::get('notifications.email_verification.url_label'))
            ->line($verificationUrl)
            ->salutation(Lang::get('notifications.email_verification.salutation'));
    }

    /**
     * Generate the email verification URL with token.
     */
    protected function generateVerificationUrl(User $user): string
    {
        // Get the scopes for unverified user
        $scopes = Config::get('roles.unverified_user.scopes', ['user:verify']);

        // Create a verification token with the appropriate scopes
        $token = $user->createToken('email-verification-token', $scopes);

        // Get the base URL from configuration
        $baseUrl = Config::get('app.urls.email_verification');

        // Generate the full verification URL with the token
        return $baseUrl . $token->accessToken;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'Email verification notification sent.',
            'user_id' => $notifiable->id,
        ];
    }
}
