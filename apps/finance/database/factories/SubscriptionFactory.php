<?php

declare(strict_types=1);

namespace Database\Factories;

use Carbon\Carbon;
use FinSi\FinanceDomain\Enums\Frequency;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\User;
use FinSi\FinanceDomain\Money\Decimal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\FinSi\FinanceDomain\Models\Subscription>
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
            'name'                  => $this->faker->words(3, true),
            'user_id'               => $user,
            'finished_at'           => $this->faker->boolean(10) ? Carbon::now()->addMonths($this->faker->numberBetween(1, 6)) : null,
            'started_at'            => $startedAt,
            'amount'                => Decimal::fromMinor($this->faker->numberBetween(10, 150000)),
            'feed_account_id'       => $feedAccount,
            'frequency_type'        => $frequencyType,
            'frequency_unit'        => $frequencyUnit,
            'next_payment_date'     => $nextPaymentDate,
            'previous_payment_date' => $previousPaymentDate,
        ];
    }
}
