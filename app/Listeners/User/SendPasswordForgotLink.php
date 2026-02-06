<?php

namespace App\Listeners\User;

use App\Events\User\PasswordForgotRequested;
use App\Notifications\User\PasswordForgotNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendPasswordForgotLink implements ShouldQueue
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
    public function handle(PasswordForgotRequested $event): void
    {
        try {
            Log::info('SendPasswordForgotLink listener started', [
                'user_id' => $event->user->id,
                'user_email' => $event->user->email,
                'queue' => $this->queue,
            ]);

            // Get user's language preference
            $locale = $event->user->getSetting('language', config('settings.language.default', 'es'));

            // Send password forgot notification with user's locale
            $event->user->notify(new PasswordForgotNotification($locale));

            Log::info('SendPasswordForgotLink listener completed successfully', [
                'user_id' => $event->user->id,
                'user_email' => $event->user->email,
                'locale' => $locale,
            ]);

        } catch (\Exception $e) {
            Log::error('SendPasswordForgotLink listener failed', [
                'user_id' => $event->user->id ?? null,
                'user_email' => $event->user->email ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
