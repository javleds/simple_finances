<?php

declare(strict_types=1);

namespace Database\Factories;

use Carbon\Carbon;
use FinSi\FinanceDomain\Models\User;
use FinSi\FinanceDomain\Money\Decimal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\FinSi\FinanceDomain\Models\Account>
 */
class AccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::factory();
        $creditCard = $this->faker->boolean(20);
        $creditLine = $creditCard ? Decimal::fromMinor($this->faker->numberBetween(100000, 1500000)) : null;
        $spent = $creditCard && null !== $creditLine ? Decimal::fromMinor($this->faker->numberBetween(0, Decimal::minor($creditLine))) : null;
        $availableCredit = $creditCard && null !== $creditLine && null !== $spent
            ? Decimal::subtract($creditLine, $spent)
            : null;
        $balance = $creditCard
            ? Decimal::negate($spent ?? '0.00')
            : Decimal::fromMinor($this->faker->numberBetween(-250000, 2500000));
        $cutoffDay = $creditCard ? $this->faker->numberBetween(1, 28) : null;
        $nextCutoffDate = $creditCard ? Carbon::now()->addDays($this->faker->numberBetween(1, 28)) : null;

        return [
            'name'             => $this->faker->words(3, true),
            'description'      => $this->faker->randomElement([null, $this->faker->text()]),
            'color'            => $this->faker->hexColor(),
            'balance'          => $balance,
            'user_id'          => $user,
            'credit_card'      => $creditCard,
            'credit_line'      => $creditLine,
            'cutoff_day'       => $cutoffDay,
            'next_cutoff_date' => $nextCutoffDate,
            'available_credit' => $availableCredit,
            'spent'            => $spent,
            'virtual'          => $this->faker->boolean(15),
        ];
    }
}
