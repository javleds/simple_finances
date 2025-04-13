<?php

namespace Database\Factories;

use App\Enums\Frequency;
use App\Models\Account;
use App\Models\User;
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
        return [
            'name' => $this->faker->words(3, true),
            'user_id' => User::factory(),
            'finished_at' => null,
            'started_at' => null,
            'amount' => $this->faker->randomFloat(2, 0.1, 1500.0),
            'feed_account_id' => null,
            'frequency_type' => $this->faker->randomElement(Frequency::values()),
            'frequency_unit' => $this->faker->randomDigit(),
        ];
    }
}
