<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CreateFakeUsers extends Command
{
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

        $startSequence = ((int) User::query()->max('id')) + 1;

        $users = collect(range(0, $count - 1))
            ->map(fn (int $offset) => User::factory()->fakeUser($startSequence + $offset)->create());

        $this->info("Created {$users->count()} fake users.");
        $this->line('First email: ' . $users->first()?->email);
        $this->line('Last email: ' . $users->last()?->email);

        return self::SUCCESS;
    }
}
