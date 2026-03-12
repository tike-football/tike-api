<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CreateFakeUsers extends Command
{
    private const PHONE_PREFIX = '+1';

    /**
     * @var string
     */
    protected $signature = 'users:create-fake {count : Number of fake users to create}';

    /**
     * @var string
     */
    protected $description = 'Create fake active users with unique @test.com emails and unique phone numbers';

    public function handle(): int
    {
        Log::info($this->getName() . ' started');

        $count = (int) $this->argument('count');

        if ($count <= 0) {
            $this->error('The count must be greater than 0.');

            return self::FAILURE;
        }

        $nextSequence = ((int) User::query()->max('id')) + 1;
        $users = collect();

        foreach (range(1, $count) as $_) {
            $nextSequence = $this->findNextAvailableSequence($nextSequence);
            $users->push(User::factory()->fakeUser($nextSequence)->create());
            $nextSequence++;
        }

        $this->info("Created {$users->count()} fake users.");
        $this->line('First email: ' . $users->first()?->email);
        $this->line('Last email: ' . $users->last()?->email);

        return self::SUCCESS;
    }

    private function findNextAvailableSequence(int $sequence): int
    {
        while ($this->sequenceAlreadyExists($sequence)) {
            $sequence++;
        }

        return $sequence;
    }

    private function sequenceAlreadyExists(int $sequence): bool
    {
        $email = 'fakeuser' . $sequence . '@test.com';
        $phoneNumber = str_pad((string) (7000000000 + $sequence), 10, '0', STR_PAD_LEFT);
        $fullPhoneNumber = self::PHONE_PREFIX . $phoneNumber;

        return User::query()
            ->where('email', $email)
            ->orWhere('full_phone_number', $fullPhoneNumber)
            ->exists();
    }
}
