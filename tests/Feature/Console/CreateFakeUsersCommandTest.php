<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateFakeUsersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_fake_users_command_creates_requested_amount_with_unique_emails_and_phone_numbers(): void
    {
        $this->artisan('users:create-fake 3')
            ->assertExitCode(0);

        $users = User::query()->orderBy('id')->get();

        $this->assertCount(3, $users);
        $this->assertSame([
            'fakeuser1@test.com',
            'fakeuser2@test.com',
            'fakeuser3@test.com',
        ], $users->pluck('email')->all());
        $this->assertSame(3, $users->pluck('email')->unique()->count());
        $this->assertSame(3, $users->pluck('full_phone_number')->unique()->count());
        $this->assertTrue($users->every(fn (User $user): bool => $user->role === 'user'));
        $this->assertTrue($users->every(fn (User $user): bool => $user->email_verified_at !== null));
    }

    public function test_create_fake_users_command_fails_when_count_is_not_positive(): void
    {
        $this->artisan('users:create-fake 0')
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_create_fake_users_command_skips_existing_fake_email_or_phone_collisions(): void
    {
        User::factory()->create([
            'email' => 'fakeuser1@test.com',
            'country_code' => '+1',
            'phone_number' => '7000000001',
            'full_phone_number' => '+17000000001',
        ]);

        $this->artisan('users:create-fake 2')
            ->assertExitCode(0);

        $emails = User::query()
            ->orderBy('id')
            ->pluck('email')
            ->all();

        $this->assertContains('fakeuser1@test.com', $emails);
        $this->assertContains('fakeuser2@test.com', $emails);
        $this->assertContains('fakeuser3@test.com', $emails);
        $this->assertCount(3, $emails);
    }
}
