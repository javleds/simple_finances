<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Enums\TransactionStatus;
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
        return [
            'concept' => $this->faker->words(3, true),
            'amount' => $this->faker->randomFloat(2, 1.0, 10000.0),
            'type' => $this->faker->randomElement(TransactionType::values()),
            'status' => TransactionStatus::Completed,
            'user_id' => User::factory(),
            'account_id' => Account::factory(),
            'scheduled_at' => Carbon::now()->format('Y-m-d'),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => TransactionStatus::Pending,
        ]);
    }
}
