<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AccountUserNotification>
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
