<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SubscriptionPayment>
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
            'scheduled_at' => $scheduledAt,
            'amount' => $this->faker->randomFloat(2, 1.0, 1200.0),
            'status' => $status,
            'subscription_id' => $subscription,
            'user_id' => $user,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => ['status' => PaymentStatus::Paid]);
    }
}
