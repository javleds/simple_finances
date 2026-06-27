<?php

use App\Enums\Frequency;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Dashboard\BuildDashboardSubscriptions;
use Carbon\CarbonImmutable;

it('returns annual subscription totals for active subscriptions', function () {
    CarbonImmutable::setTestNow('2026-07-01');
    $createSubscription = fn (array $attributes): Subscription => Subscription::withoutEvents(
        fn (): Subscription => Subscription::factory()->create($attributes)
    );

    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $createSubscription([
        'amount' => 1000,
        'frequency_type' => Frequency::Year,
        'frequency_unit' => 1,
        'finished_at' => null,
        'next_payment_date' => '2027-01-01',
        'started_at' => '2026-01-01',
        'user_id' => $otherUser->id,
    ]);

    $this->actingAs($user);

    $createSubscription([
        'amount' => 100,
        'frequency_type' => Frequency::Month,
        'frequency_unit' => 1,
        'finished_at' => null,
        'next_payment_date' => '2026-07-15',
        'started_at' => '2026-06-15',
        'user_id' => $user->id,
    ]);
    $createSubscription([
        'amount' => 1200,
        'frequency_type' => Frequency::Year,
        'frequency_unit' => 1,
        'finished_at' => null,
        'next_payment_date' => '2027-01-01',
        'started_at' => '2026-01-01',
        'user_id' => $user->id,
    ]);
    $createSubscription([
        'amount' => 10,
        'frequency_type' => Frequency::Day,
        'frequency_unit' => 1,
        'finished_at' => null,
        'next_payment_date' => '2026-07-02',
        'started_at' => '2026-07-01',
        'user_id' => $user->id,
    ]);
    $createSubscription([
        'amount' => 1000,
        'frequency_type' => Frequency::Year,
        'frequency_unit' => 1,
        'finished_at' => now(),
        'next_payment_date' => '2027-01-01',
        'started_at' => '2026-01-01',
        'user_id' => $user->id,
    ]);

    $data = app(BuildDashboardSubscriptions::class)->execute();

    expect($data)->toBe([
        'annual_total' => 6050.0,
        'subscriptions_count' => 3,
        'savings_target_today' => 648.4,
        'upcoming_commitment' => 1310.0,
        'nearest_payment' => [
            'subscription_id' => $data['nearest_payment']['subscription_id'],
            'name' => $data['nearest_payment']['name'],
            'amount' => 10.0,
            'next_payment_date' => '2026-07-02',
            'cycle_start_date' => '2026-07-01',
            'target_today' => 0.0,
        ],
    ]);

    CarbonImmutable::setTestNow();
});

it('calculates the healthy savings target for flexible subscription cycles', function () {
    CarbonImmutable::setTestNow('2026-07-01');
    $createSubscription = fn (array $attributes): Subscription => Subscription::withoutEvents(
        fn (): Subscription => Subscription::factory()->create($attributes)
    );

    $user = User::factory()->create();
    $this->actingAs($user);

    $createSubscription([
        'name' => 'Annual service',
        'amount' => 2400,
        'frequency_type' => Frequency::Year,
        'frequency_unit' => 1,
        'finished_at' => null,
        'started_at' => '2026-06-01',
        'next_payment_date' => '2027-06-01',
        'user_id' => $user->id,
    ]);
    $createSubscription([
        'name' => 'Four month service',
        'amount' => 1200,
        'frequency_type' => Frequency::Month,
        'frequency_unit' => 4,
        'finished_at' => null,
        'started_at' => '2026-06-01',
        'next_payment_date' => '2026-10-01',
        'user_id' => $user->id,
    ]);
    $createSubscription([
        'name' => 'Finished service',
        'amount' => 999,
        'frequency_type' => Frequency::Month,
        'frequency_unit' => 1,
        'finished_at' => '2026-06-01',
        'started_at' => '2026-06-01',
        'next_payment_date' => '2026-07-01',
        'user_id' => $user->id,
    ]);

    $data = app(BuildDashboardSubscriptions::class)->execute();

    expect($data['savings_target_today'])->toBe(492.34)
        ->and($data['upcoming_commitment'])->toBe(3600.0)
        ->and($data['subscriptions_count'])->toBe(2)
        ->and($data['nearest_payment']['name'])->toBe('Four month service')
        ->and($data['nearest_payment']['target_today'])->toBe(295.08);

    CarbonImmutable::setTestNow();
});
