<?php

use App\Enums\Frequency;
use App\Models\Subscription;
use App\Services\Subscriptions\UpdateNextPayment;
use Carbon\CarbonImmutable;

it('updates the subscription yearly', function (?string $nextPaymentDate, string $startedAt, string $referenceDate, string $expectedNextPaymentDate) {
    $subscription = Subscription::factory()->createQuietly([
        'frequency_type' => Frequency::Year,
        'frequency_unit' => 1,
        'next_payment_date' => $nextPaymentDate,
        'started_at' => $startedAt,
    ]);

    $subscription = app(UpdateNextPayment::class)->handle(
        $subscription,
        CarbonImmutable::createFromFormat('Y-m-d', $referenceDate)->startOfDay()
    );

    expect($subscription->next_payment_date->format('Y-m-d'))->toBe($expectedNextPaymentDate);
})->with([
    'create the next day from future reference' => [null, '2020-05-15', '2021-05-12', '2021-05-15'],
    'create the next day from same reference' => [null, '2021-05-15', '2022-05-15', '2022-05-15'],
    'create the next day from past reference' => [null, '2022-05-15', '2022-06-15', '2023-05-15'],
    'calculate date when next payment is still valid' => ['2025-05-15', '2023-05-15', '2024-06-15', '2025-05-15'],
    'calculate date when next payment is past' => ['2020-05-15', '2019-05-15', '2020-10-01', '2021-05-15'],
]);

it('updates the subscription monthly', function (?string $nextPaymentDate, string $startedAt, string $referenceDate, string $expectedNextPaymentDate) {
    $subscription = Subscription::factory()->createQuietly([
        'frequency_type' => Frequency::Month,
        'frequency_unit' => 1,
        'next_payment_date' => $nextPaymentDate,
        'started_at' => $startedAt,
    ]);

    $subscription = app(UpdateNextPayment::class)->handle(
        $subscription,
        CarbonImmutable::createFromFormat('Y-m-d', $referenceDate)->startOfDay()
    );

    expect($subscription->next_payment_date->format('Y-m-d'))->toBe($expectedNextPaymentDate);
})->with([
    'create the next day from future reference' => [null, '2020-05-15', '2020-06-12', '2020-06-15'],
    'create the next day from same reference' => [null, '2021-05-15', '2021-07-15', '2021-07-15'],
    'create the next day from past reference' => [null, '2022-05-15', '2022-07-16', '2022-08-15'],
    'calculate date when next payment is still valid' => ['2023-08-15', '2023-05-15', '2023-05-15', '2023-08-15'],
    'calculate date when next payment is past' => ['2024-09-15', '2024-05-15', '2024-10-15', '2024-10-15'],
]);
