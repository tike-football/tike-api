<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CreateFakeUsers extends Command
{
    private const PHONE_PREFIX = '+1';
    private const AVATARS = [
        'system/default01.png',
        'system/default02.png',
        'system/default03.png',
        'system/default04.png',
        'system/default05.png',
        'system/default06.png',
    ];
    private const FIRST_NAMES = [
        'Alex', 'Sam', 'Chris', 'Jordan', 'Taylor', 'Morgan',
        'Cameron', 'Jamie', 'Drew', 'Logan', 'Casey', 'Riley',
    ];
    private const LAST_NAMES = [
        'Smith', 'Johnson', 'Brown', 'Davis', 'Miller', 'Wilson',
        'Moore', 'Taylor', 'Anderson', 'Thomas', 'Jackson', 'White',
    ];

    private static ?string $passwordHash = null;

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
            $user = new User();
            $user->forceFill($this->buildFakeUserAttributes($nextSequence));
            $user->save();
            $users->push($user);
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

    /**
     * @return array<string, mixed>
     */
    private function buildFakeUserAttributes(int $sequence): array
    {
        $phoneNumber = str_pad((string) (7000000000 + $sequence), 10, '0', STR_PAD_LEFT);

        return [
            'name' => $this->pickValue(self::FIRST_NAMES, $sequence),
            'last_name' => $this->pickValue(self::LAST_NAMES, $sequence),
            'email' => 'fakeuser' . $sequence . '@test.com',
            'country_code' => self::PHONE_PREFIX,
            'phone_number' => $phoneNumber,
            'full_phone_number' => self::PHONE_PREFIX . $phoneNumber,
            'email_verified_at' => now(),
            'password' => self::$passwordHash ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => 'user',
            'avatar_path' => $this->pickValue(self::AVATARS, $sequence),
        ];
    }

    /**
     * @param list<string> $values
     */
    private function pickValue(array $values, int $sequence): string
    {
        $index = ($sequence - 1) % count($values);

        return $values[$index];
    }
}
