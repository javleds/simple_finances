<?php

namespace Database\Factories;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
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
        $type = $this->faker->randomElement(TransactionType::cases());
        $amount = $this->faker->randomFloat(2, 1.0, 10000.0);
        $scheduledAt = Carbon::now()->subDays($this->faker->numberBetween(0, 30));

        return [
            'concept' => $this->faker->words(3, true),
            'amount' => $amount,
            'percentage' => $this->faker->randomFloat(2, 10.0, 100.0),
            'type' => $type,
            'status' => TransactionStatus::Completed,
            'user_id' => $user,
            'account_id' => $account,
            'scheduled_at' => $scheduledAt,
            'parent_transaction_id' => null,
            'financial_goal_id' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => TransactionStatus::Pending,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => TransactionStatus::Completed,
        ]);
    }

    public function income(): static
    {
        return $this->state(fn () => [
            'type' => TransactionType::Income,
        ]);
    }

    public function outcome(): static
    {
        return $this->state(fn () => [
            'type' => TransactionType::Outcome,
        ]);
    }
}
