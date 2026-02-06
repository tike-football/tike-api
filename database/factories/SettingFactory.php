<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Setting>
 */
class SettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'key' => 'language',
            'value' => fake()->randomElement(['es', 'en']),
        ];
    }

    /**
     * Indicate that the setting is for language.
     */
    public function language(string $value = 'es'): static
    {
        return $this->state(fn (array $attributes) => [
            'key' => 'language',
            'value' => $value,
        ]);
    }
}
