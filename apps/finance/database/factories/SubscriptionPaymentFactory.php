<?php

declare(strict_types=1);

namespace Database\Factories;

use Carbon\Carbon;
use FinSi\FinanceDomain\Enums\PaymentStatus;
use FinSi\FinanceDomain\Models\Subscription;
use FinSi\FinanceDomain\Models\User;
use FinSi\FinanceDomain\Money\Decimal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\FinSi\FinanceDomain\Models\SubscriptionPayment>
 */
class SubscriptionPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::factory();
        $subscription = Subscription::factory()->state(fn () => ['user_id' => $user]);
        $status = $this->faker->randomElement(PaymentStatus::cases());
        $scheduledAt = Carbon::now()->addDays($this->faker->numberBetween(1, 60));

        return [
            'scheduled_at'    => $scheduledAt,
            'amount'          => Decimal::fromMinor($this->faker->numberBetween(100, 120000)),
            'status'          => $status,
            'subscription_id' => $subscription,
            'user_id'         => $user,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => ['status' => PaymentStatus::Paid]);
    }
}
