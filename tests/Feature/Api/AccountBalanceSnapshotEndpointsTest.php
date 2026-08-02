<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Auth\JwtTokenService;

function virtualAccountApiHeaders(User $user): array
{
    $token = app(JwtTokenService::class)->generate($user)['token'];

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
        'amount' => 10000.0,
        'scheduled_at' => '2026-08-01',
    ]);
    $account->updateBalance();

    $this
        ->withHeaders(virtualAccountApiHeaders($user))
        ->postJson("/api/accounts/{$account->id}/balance-snapshots", [
            'observed_balance' => 10300.0,
            'observed_at' => '2026-08-02',
            'notes' => 'Monthly statement',
        ])
        ->assertCreated()
        ->assertJsonPath('data.observed_balance', 10300.0)
        ->assertJsonPath('data.previous_balance', 10000.0)
        ->assertJsonPath('data.delta', 300.0)
        ->assertJsonPath('meta.account.balance', 10300.0);

    $this
        ->withHeaders(virtualAccountApiHeaders($user))
        ->getJson("/api/accounts/{$account->id}/balance-snapshots")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.delta', 300.0);
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
        'amount' => 10000.0,
        'scheduled_at' => '2026-08-01',
    ]);
    $account->updateBalance();

    $this
        ->withHeaders(virtualAccountApiHeaders($user))
        ->postJson("/api/accounts/{$account->id}/balance-snapshots", [
            'observed_balance' => 10300.0,
            'observed_at' => '2026-08-02',
        ])
        ->assertCreated();

    $this
        ->withHeaders(virtualAccountApiHeaders($user))
        ->getJson('/api/virtual-accounts')
        ->assertOk()
        ->assertJsonPath('data.summary.current_balance', 10300.0)
        ->assertJsonPath('data.summary.observed_yield', 300.0)
        ->assertJsonPath('data.accounts.0.account_name', 'Investment pocket')
        ->assertJsonPath('data.accounts.0.observed_yield', 300.0);
});
