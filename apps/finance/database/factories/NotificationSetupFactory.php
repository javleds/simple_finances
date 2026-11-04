<?php

namespace Database\Factories;

use FinSi\FinanceDomain\Models\NotificationType;
use FinSi\FinanceDomain\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\FinSi\FinanceDomain\Models\NotificationSetup>
 */
class NotificationSetupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'notification_type_id' => NotificationType::factory(),
        ];
    }
}
