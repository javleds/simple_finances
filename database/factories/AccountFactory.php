<?php

namespace Database\Factories;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Account>
 */
class AccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::factory();
        $creditCard = $this->faker->boolean(20);
        $creditLine = $creditCard ? $this->faker->randomFloat(2, 1000, 15000) : null;
        $spent = $creditCard && $creditLine !== null ? $this->faker->randomFloat(2, 0, $creditLine) : null;
        $availableCredit = $creditCard && $creditLine !== null && $spent !== null
            ? round($creditLine - $spent, 2)
            : null;
        $balance = $creditCard
            ? round(($spent ?? 0) * -1, 2)
            : $this->faker->randomFloat(2, -2500, 25000);
        $cutoffDay = $creditCard ? $this->faker->numberBetween(1, 28) : null;
        $nextCutoffDate = $creditCard ? Carbon::now()->addDays($this->faker->numberBetween(1, 28)) : null;

        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->randomElement([null, $this->faker->text()]),
            'color' => $this->faker->hexColor(),
            'balance' => $balance,
            'user_id' => $user,
            'credit_card' => $creditCard,
            'credit_line' => $creditLine,
            'cutoff_day' => $cutoffDay,
            'next_cutoff_date' => $nextCutoffDate,
            'available_credit' => $availableCredit,
            'spent' => $spent,
            'virtual' => $this->faker->boolean(15),
        ];
    }
}
