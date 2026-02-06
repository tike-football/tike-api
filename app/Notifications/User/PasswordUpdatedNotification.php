<?php

namespace App\Notifications\User;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\App;

class PasswordUpdatedNotification extends Notification implements ShouldQueue
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

        $mailMessage = (new MailMessage)
            ->subject(__('notifications.password_updated.subject'))
            ->greeting(__('notifications.password_updated.greeting', ['name' => $notifiable->name]))
            ->line(__('notifications.password_updated.line1'))
            ->line(__('notifications.password_updated.line2'))
            ->line(__('notifications.password_updated.line3'))
            ->line(__('notifications.password_updated.line4'))
            ->salutation(__('notifications.password_updated.salutation'));

        // Restore previous locale
        App::setLocale($previousLocale);

        return $mailMessage;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'Password updated notification sent.',
            'user_id' => $notifiable->id,
        ];
    }
}
