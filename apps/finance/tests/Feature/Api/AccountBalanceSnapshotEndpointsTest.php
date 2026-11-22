<?php

use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Models\User;

function virtualAccountApiHeaders(User $user): array
{
    $token = \Tests\Support\AcceptedUserToken::generate($user)['token'];

    return [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer '.$token,
    ];
}

it('captures and lists observed balance snapshots for a virtual account', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create([
        'user_id' => $user->id,
        'virtual' => true,
        'credit_card' => false,
    ]);
    $account->users()->attach($user->id);
    Transaction::factory()->income()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
        'amount' => '10000.00',
        'scheduled_at' => '2026-08-01',
    ]);
    app(\App\Services\Accounts\RecalculateAccountBalance::class)->execute($account)->balance;

    $this
        ->withHeaders(virtualAccountApiHeaders($user))
        ->postJson("/api/accounts/{$account->id}/balance-snapshots", [
            'observed_balance' => '10300.00',
            'observed_at' => '2026-08-02',
            'notes' => 'Monthly statement',
        ])
        ->assertCreated()
        ->assertJsonPath('data.observed_balance', '10300.00')
        ->assertJsonPath('data.previous_balance', '10000.00')
        ->assertJsonPath('data.delta', '300.00')
        ->assertJsonPath('meta.account.balance', '10300.00');

    $this
        ->withHeaders(virtualAccountApiHeaders($user))
        ->getJson("/api/accounts/{$account->id}/balance-snapshots")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.delta', '300.00');
});

it('returns virtual account summaries with observed yield separated from capital movement', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create([
        'name' => 'Investment pocket',
        'user_id' => $user->id,
        'virtual' => true,
        'credit_card' => false,
    ]);
    $account->users()->attach($user->id);
    Transaction::factory()->income()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
        'amount' => '10000.00',
        'scheduled_at' => '2026-08-01',
    ]);
    app(\App\Services\Accounts\RecalculateAccountBalance::class)->execute($account)->balance;

    $this
        ->withHeaders(virtualAccountApiHeaders($user))
        ->postJson("/api/accounts/{$account->id}/balance-snapshots", [
            'observed_balance' => '10300.00',
            'observed_at' => '2026-08-02',
        ])
        ->assertCreated();

    $this
        ->withHeaders(virtualAccountApiHeaders($user))
        ->getJson('/api/virtual-accounts')
        ->assertOk()
        ->assertJsonPath('data.summary.current_balance', '10300.00')
        ->assertJsonPath('data.summary.observed_yield', '300.00')
        ->assertJsonPath('data.accounts.0.account_name', 'Investment pocket')
        ->assertJsonPath('data.accounts.0.observed_yield', '300.00');
});
