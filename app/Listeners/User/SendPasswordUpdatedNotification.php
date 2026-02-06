<?php

namespace App\Listeners\User;

use App\Events\User\PasswordUpdated;
use App\Notifications\User\PasswordUpdatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendPasswordUpdatedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The name of the queue the job should be sent to.
     *
     * @var string|null
     */
    public $queue = 'emails';

    /**
     * Determine if the listener should be queued after the response is sent to the browser.
     *
     * @var bool
     */
    public $afterResponse = false;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(PasswordUpdated $event): void
    {
        try {
            $user = $event->user;

            Log::info('SendPasswordUpdatedNotification listener started', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'timestamp' => now()->toDateTimeString()
            ]);

            // Get user's language preference
            $locale = $user->getSetting('language', config('settings.language.default', 'es'));

            // Send password updated notification with user's locale
            $user->notify(new PasswordUpdatedNotification($locale));

            Log::info('SendPasswordUpdatedNotification listener completed successfully', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'locale' => $locale,
            ]);
        } catch (\Exception $e) {
            Log::error('SendPasswordUpdatedNotification listener failed', [
                'user_id' => $event->user->id ?? null,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString()
            ]);

            // Re-throw the exception so the job can be retried
            throw $e;
        }
    }
}
