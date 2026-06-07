<?php

namespace Database\Factories;

use App\Enums\FixedOutcomeType;
use App\Models\FixedIncome;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FixedOutcome>
 */
class FixedOutcomeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fixed_income_id' => FixedIncome::factory(),
            'name' => $this->faker->words(3, true),
            'amount' => $this->faker->randomFloat(2, 1, 1000),
            'type' => $this->faker->randomElement(FixedOutcomeType::cases()),
            'user_id' => User::factory(),
        ];
    }
}
