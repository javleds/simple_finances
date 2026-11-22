<?php

declare(strict_types=1);

use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Models\User;
use Tests\Support\AcceptedUserToken;

function exactMoneyApiScenario(): array
{
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id, 'balance' => '0.00', 'credit_card' => false, 'virtual' => false]);
    $account->users()->sync([$user->id => ['percentage' => '100.00']]);

    return [$user, $account, AcceptedUserToken::generate($user)['token']];
}

function exactMoneyApiPayload(mixed $amount): array
{
    return [
        'type' => 'income',
        'status' => 'completed',
        'concept' => 'Exact decimal contract',
        'amount' => $amount,
        'scheduled_at' => now()->toDateString(),
        'split_between_users' => false,
    ];
}

it('rejects approximate or invalid monetary input before writing', function (mixed $amount): void {
    [, $account, $token] = exactMoneyApiScenario();
    $this->withToken($token)
        ->postJson("/api/accounts/{$account->id}/transactions", exactMoneyApiPayload($amount))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('amount');

    expect(Transaction::query()->where('account_id', $account->id)->count())->toBe(0);
    expect($account->fresh()->balance)->toBe('0.00');
})->with([
    'JSON floating point number' => [1.23],
    'JSON integer number' => [123],
    'exponent string' => ['1e2'],
    'excess precision' => ['1.001'],
    'outside transaction column range' => ['100000000.00'],
]);

it('rejects numeric and overprecise financial percentages', function (mixed $percentage): void {
    [$user, $account, $token] = exactMoneyApiScenario();
    $this->withToken($token)
        ->postJson("/api/accounts/{$account->id}/transactions", [
            ...exactMoneyApiPayload('1.00'),
            'type' => 'outcome',
            'split_between_users' => true,
            'user_payments' => [['user_id' => $user->id, 'percentage' => $percentage]],
        ])->assertUnprocessable()->assertJsonValidationErrors('user_payments.0.percentage');

    expect(Transaction::query()->where('account_id', $account->id)->count())->toBe(0);
})->with([
    'numeric percentage' => [100],
    'overprecise percentage' => ['100.001'],
]);

it('preserves exact decimal input through persistence and response metadata', function (): void {
    [, $account, $token] = exactMoneyApiScenario();
    $this->withToken($token)
        ->postJson("/api/accounts/{$account->id}/transactions", exactMoneyApiPayload('0.10'))
        ->assertCreated()
        ->assertJsonPath('data.amount', '0.10')
        ->assertJsonPath('meta.account.balance', '0.10');
    $this->withToken($token)
        ->postJson("/api/accounts/{$account->id}/transactions", exactMoneyApiPayload('0.20'))
        ->assertCreated()
        ->assertJsonPath('data.amount', '0.20')
        ->assertJsonPath('meta.account.balance', '0.30');

    expect($account->fresh()->balance)->toBe('0.30');
});

it('requires the monetary contract revision before an authenticated write', function (): void {
    [, $account, $token] = exactMoneyApiScenario();
    $this->flushHeaders()->withToken($token)
        ->postJson("/api/accounts/{$account->id}/transactions", exactMoneyApiPayload('1.00'))
        ->assertConflict();

    expect(Transaction::query()->where('account_id', $account->id)->count())->toBe(0);
});

it('preserves authentication failures for monetary routes', function (): void {
    [, $account] = exactMoneyApiScenario();
    $this->flushHeaders()
        ->postJson("/api/accounts/{$account->id}/transactions", exactMoneyApiPayload('1.00'))
        ->assertUnauthorized();
});

it('rejects a split without participant allocations on every transaction endpoint', function (string $endpoint, array $overrides): void {
    [, $account, $token] = exactMoneyApiScenario();
    $url = $endpoint === 'global' ? '/api/transactions' : "/api/accounts/{$account->id}/transactions";
    $this->withToken($token)->postJson($url, [
        ...exactMoneyApiPayload('1.00'),
        'account_id' => $account->id,
        'type' => 'outcome',
        'split_between_users' => true,
        ...$overrides,
    ])->assertUnprocessable()->assertJsonValidationErrors('user_payments');

    expect(Transaction::query()->where('account_id', $account->id)->count())->toBe(0);
})->with(['global', 'nested'])->with([
    'absent' => [[]],
    'null' => [['user_payments' => null]],
    'empty' => [['user_payments' => []]],
]);
