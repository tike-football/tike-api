<?php

namespace App\Notifications\User;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;

class PasswordForgotNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected string $userLocale;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $locale = 'es')
    {
        $this->userLocale = $locale;
        $this->locale($locale);
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
        // Temporarily set the locale for this notification
        $previousLocale = App::getLocale();
        App::setLocale($this->userLocale);

        $resetUrl = $this->generateResetUrl($notifiable);

        $mailMessage = (new MailMessage)
            ->subject(__('notifications.password_forgot.subject'))
            ->greeting(__('notifications.password_forgot.greeting', ['name' => $notifiable->name]))
            ->line(__('notifications.password_forgot.line1'))
            ->line(__('notifications.password_forgot.line2'))
            ->action(__('notifications.password_forgot.action'), $resetUrl)
            ->line(__('notifications.password_forgot.line3'))
            ->line(__('notifications.password_forgot.line4'))
            ->line(__('notifications.password_forgot.url_label'))
            ->line($resetUrl)
            ->salutation(__('notifications.password_forgot.salutation'));

        // Restore previous locale
        App::setLocale($previousLocale);

        return $mailMessage;
    }

    /**
     * Generate the password reset URL with token.
     */
    protected function generateResetUrl(User $user): string
    {
        // Get the scopes for password reset
        $scopes = Config::get('roles.unverified_user.scopes', ['user:verify']);

        // Create a reset token with the appropriate scopes
        $token = $user->createToken('password-reset-token', $scopes);

        // Get the base URL from configuration
        $baseUrl = Config::get('app.urls.reset_password');

        // Generate the full reset URL with the token
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
            'message' => 'Password reset notification sent.',
            'user_id' => $notifiable->id,
        ];
    }
}
