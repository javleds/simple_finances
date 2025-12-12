<?php

namespace Database\Factories;

use App\Enums\FinancialGoalStatus;
use App\Models\Account;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FinancialGoal>
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
        $amount = $this->faker->randomFloat(2, 100.0, 25000.0);
        $progress = $this->faker->numberBetween(0, 100);

        return [
            'name' => $this->faker->words(3, true),
            'amount' => $amount,
            'progress' => $progress,
            'must_completed_at' => $this->faker->boolean(60) ? Carbon::now()->addMonths($this->faker->numberBetween(1, 12)) : null,
            'status' => $progress >= 100 ? FinancialGoalStatus::Completed : FinancialGoalStatus::InProgress,
            'account_id' => $account,
            'user_id' => $user,
        ];
    }
}
