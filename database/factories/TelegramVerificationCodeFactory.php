<?php

namespace Database\Factories;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TelegramVerificationCode>
 */
class TelegramVerificationCodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::factory();

        return [
            'user_id' => $user,
            'code' => $this->faker->unique()->numerify('######'),
            'expires_at' => Carbon::now()->addMinutes(10),
            'used_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => Carbon::now()->subMinutes(5)]);
    }

    public function used(): static
    {
        return $this->state(fn () => ['used_at' => Carbon::now()]);
    }
}
