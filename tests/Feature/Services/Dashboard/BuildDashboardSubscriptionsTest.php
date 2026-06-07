<?php

use App\Enums\Frequency;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Dashboard\BuildDashboardSubscriptions;

it('returns annual subscription totals for active subscriptions', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    Subscription::factory()->create([
        'amount' => 1000,
        'frequency_type' => Frequency::Year,
        'frequency_unit' => 1,
        'finished_at' => null,
        'user_id' => $otherUser->id,
    ]);

    $this->actingAs($user);

    Subscription::factory()->create([
        'amount' => 100,
        'frequency_type' => Frequency::Month,
        'frequency_unit' => 1,
        'finished_at' => null,
        'user_id' => $user->id,
    ]);
    Subscription::factory()->create([
        'amount' => 1200,
        'frequency_type' => Frequency::Year,
        'frequency_unit' => 1,
        'finished_at' => null,
        'user_id' => $user->id,
    ]);
    Subscription::factory()->create([
        'amount' => 10,
        'frequency_type' => Frequency::Day,
        'frequency_unit' => 1,
        'finished_at' => null,
        'user_id' => $user->id,
    ]);
    Subscription::factory()->create([
        'amount' => 1000,
        'frequency_type' => Frequency::Year,
        'frequency_unit' => 1,
        'finished_at' => now(),
        'user_id' => $user->id,
    ]);

    $data = app(BuildDashboardSubscriptions::class)->execute();

    expect($data)->toBe([
        'annual_total' => 6050.0,
        'subscriptions_count' => 3,
    ]);
});
