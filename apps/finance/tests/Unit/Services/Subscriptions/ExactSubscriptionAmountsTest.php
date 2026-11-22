<?php

declare(strict_types=1);

use App\Services\Subscriptions\BuildSubscriptionSavingsTarget;
use App\Services\Subscriptions\CalculateAnnualSubscriptionAmount;
use Carbon\CarbonImmutable;
use FinSi\FinanceDomain\Enums\Frequency;
use FinSi\FinanceDomain\Models\Subscription;

it('rounds the annual rational amount once after applying the frequency', function (): void {
    $subscription = (new Subscription())->setDateFormat('Y-m-d H:i:s')->forceFill([
        'amount' => '0.01', 'frequency_type' => Frequency::Day, 'frequency_unit' => 3,
    ]);

    expect((new CalculateAnnualSubscriptionAmount())->execute($subscription))->toBe('1.22');
});

it('calculates a leap year savings target from exact day ratios', function (): void {
    $subscription = (new Subscription())->setDateFormat('Y-m-d H:i:s')->forceFill([
        'id'             => 1, 'name' => 'Annual service', 'amount' => '100.01',
        'frequency_type' => Frequency::Year, 'frequency_unit' => 1,
        'started_at'     => '2024-01-01', 'next_payment_date' => '2025-01-01',
    ]);
    $target = (new BuildSubscriptionSavingsTarget())->execute($subscription, CarbonImmutable::parse('2024-07-02'));

    expect($target?->amount)->toBe('100.01')->and($target?->targetToday)->toBe('50.01');
});

it('formats large and negative money values without losing digits', function (): void {
    expect(as_money('99999999999999999.99'))->toBe('$ 99,999,999,999,999,999.99')
        ->and(as_money('-0.01'))->toBe('-$ 0.01')
        ->and(as_money('-0.00'))->toBe('$ 0.00');
});
