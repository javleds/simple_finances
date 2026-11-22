<?php

declare(strict_types=1);

namespace Database\Factories;

use FinSi\FinanceDomain\Enums\FixedOutcomeType;
use FinSi\FinanceDomain\Models\FixedIncome;
use FinSi\FinanceDomain\Models\User;
use FinSi\FinanceDomain\Money\Decimal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\FinSi\FinanceDomain\Models\FixedOutcome>
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
            'name'            => $this->faker->words(3, true),
            'amount'          => Decimal::fromMinor($this->faker->numberBetween(100, 100000)),
            'type'            => $this->faker->randomElement(FixedOutcomeType::cases()),
            'user_id'         => User::factory(),
        ];
    }
}
