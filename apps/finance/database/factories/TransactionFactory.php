<?php

declare(strict_types=1);

namespace Database\Factories;

use Carbon\Carbon;
use FinSi\FinanceDomain\Enums\TransactionStatus;
use FinSi\FinanceDomain\Enums\TransactionType;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\User;
use FinSi\FinanceDomain\Money\Decimal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\FinSi\FinanceDomain\Models\Transaction>
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
        $amount = Decimal::fromMinor($this->faker->numberBetween(100, 1000000));
        $scheduledAt = Carbon::now()->subDays($this->faker->numberBetween(0, 30));

        return [
            'concept'               => $this->faker->words(3, true),
            'amount'                => $amount,
            'percentage'            => Decimal::fromMinor($this->faker->numberBetween(1000, 10000)),
            'type'                  => $type,
            'status'                => TransactionStatus::Completed,
            'user_id'               => $user,
            'account_id'            => $account,
            'scheduled_at'          => $scheduledAt,
            'parent_transaction_id' => null,
            'financial_goal_id'     => null,
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
