<?php

namespace App\Listeners\User;

use App\Events\User\UserStored;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendEmailVerification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The name of the queue the job should be sent to.
     *
     * @var string
     */
    public $queue = 'emails';

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
    public function handle(UserStored $event): void
    {
        try {
            Log::info('SendEmailVerification listener started', [
                'user_id' => $event->user->id,
                'user_email' => $event->user->email,
                'queue' => $this->queue,
            ]);

            // TODO: Implement email verification sending logic here

            Log::info('SendEmailVerification listener completed successfully', [
                'user_id' => $event->user->id,
                'user_email' => $event->user->email,
            ]);

        } catch (\Exception $e) {
            Log::error('SendEmailVerification listener failed', [
                'user_id' => $event->user->id ?? null,
                'user_email' => $event->user->email ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
