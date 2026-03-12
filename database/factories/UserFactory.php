<?php

namespace Database\Factories;

use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    protected ?Generator $fakerGenerator = null;

    /**
     * Monotonic sequence for fake users when an explicit index is not provided.
     */
    protected static int $fakeSequence = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $faker = $this->faker();
        $countryCode = $faker->randomElement(['+1', '+52', '+34', '+44', '+49', '+33']);
        $phoneNumber = $faker->numerify('##########');
        
        return [
            'name' => $faker->firstName(),
            'last_name' => $faker->lastName(),
            'email' => $faker->unique()->safeEmail(),
            'country_code' => $countryCode,
            'phone_number' => $phoneNumber,
            'full_phone_number' => $countryCode . $phoneNumber,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function fakeUser(?int $sequence = null): static
    {
        return $this->state(function (array $attributes) use ($sequence): array {
            $faker = $this->faker();
            $index = $sequence ?? ++static::$fakeSequence;
            $phoneNumber = $this->buildFakePhoneNumber($index);

            return [
                'name' => $faker->firstName(),
                'last_name' => $faker->lastName(),
                'email' => $this->buildFakeEmail($index),
                'country_code' => '+1',
                'phone_number' => $phoneNumber,
                'full_phone_number' => '+1' . $phoneNumber,
                'email_verified_at' => now(),
                'role' => 'user',
                'avatar_path' => $faker->randomElement([
                    'system/default01.png',
                    'system/default02.png',
                    'system/default03.png',
                    'system/default04.png',
                    'system/default05.png',
                    'system/default06.png',
                ]),
            ];
        });
    }

    private function buildFakeEmail(int $index): string
    {
        return 'fakeuser' . $index . '@test.com';
    }

    private function buildFakePhoneNumber(int $index): string
    {
        return str_pad((string) (7000000000 + $index), 10, '0', STR_PAD_LEFT);
    }

    private function faker(): Generator
    {
        if ($this->fakerGenerator === null) {
            $this->fakerGenerator = FakerFactory::create();
        }

        return $this->fakerGenerator;
    }
}
