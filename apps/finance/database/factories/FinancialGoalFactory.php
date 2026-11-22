<?php

declare(strict_types=1);

namespace Database\Factories;

use Carbon\Carbon;
use FinSi\FinanceDomain\Enums\FinancialGoalStatus;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\User;
use FinSi\FinanceDomain\Money\Decimal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\FinSi\FinanceDomain\Models\FinancialGoal>
 */
class FinancialGoalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::factory();
        $account = Account::factory()->state(fn () => ['user_id' => $user]);
        $amount = Decimal::fromMinor($this->faker->numberBetween(10000, 2500000));
        $progress = Decimal::fromMinor($this->faker->numberBetween(0, 10000));

        return [
            'name'              => $this->faker->words(3, true),
            'amount'            => $amount,
            'achieved_amount'   => '0.00',
            'progress'          => $progress,
            'must_completed_at' => $this->faker->boolean(60) ? Carbon::now()->addMonths($this->faker->numberBetween(1, 12)) : null,
            'status'            => $progress >= 100 ? FinancialGoalStatus::Completed : FinancialGoalStatus::InProgress,
            'account_id'        => $account,
            'user_id'           => $user,
        ];
    }
}
