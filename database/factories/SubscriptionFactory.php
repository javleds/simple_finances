<?php

namespace Database\Factories;

use App\Enums\Frequency;
use App\Models\Account;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::factory();
        $frequencyType = $this->faker->randomElement(Frequency::cases());
        $frequencyUnit = $this->faker->numberBetween(1, 12);
        $startedAt = Carbon::now()->subDays($this->faker->numberBetween(0, 45))->startOfDay();
        $nextPaymentDate = $startedAt->clone()->add($frequencyUnit, $frequencyType->value);
        $previousPaymentDate = $startedAt->clone();
        $feedAccount = $this->faker->boolean() ? Account::factory()->state(fn () => ['user_id' => $user]) : null;

        return [
            'name' => $this->faker->words(3, true),
            'user_id' => $user,
            'finished_at' => $this->faker->boolean(10) ? Carbon::now()->addMonths($this->faker->numberBetween(1, 6)) : null,
            'started_at' => $startedAt,
            'amount' => $this->faker->randomFloat(2, 0.1, 1500.0),
            'feed_account_id' => $feedAccount,
            'frequency_type' => $frequencyType,
            'frequency_unit' => $frequencyUnit,
            'next_payment_date' => $nextPaymentDate,
            'previous_payment_date' => $previousPaymentDate,
        ];
    }
}
