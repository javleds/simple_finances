<?php

namespace Database\Factories;

use App\Enums\FixedIncomeFrequency;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FixedIncome>
 */
class FixedIncomeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'frequency' => $this->faker->randomElement(FixedIncomeFrequency::cases()),
            'user_id' => User::factory(),
        ];
    }
}
