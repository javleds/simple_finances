<?php

namespace Database\Factories;

use FinSi\FinanceDomain\Enums\InviteStatus;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\FinSi\FinanceDomain\Models\AccountInvite>
 */
class AccountInviteFactory extends Factory
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

        return [
            'email' => $this->faker->unique()->safeEmail(),
            'status' => InviteStatus::Pending,
            'account_id' => $account,
            'user_id' => $user,
            'percentage' => $this->faker->randomFloat(2, 0, 100),
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn () => ['status' => InviteStatus::Accepted]);
    }

    public function declined(): static
    {
        return $this->state(fn () => ['status' => InviteStatus::Declined]);
    }
}
