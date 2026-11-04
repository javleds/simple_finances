<?php

namespace Database\Factories;

use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\FinSi\FinanceDomain\Models\AccountUserNotification>
 */
class AccountUserNotificationFactory extends Factory
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
            'user_id' => $user,
            'account_id' => $account,
        ];
    }
}
