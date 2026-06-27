<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Auth\JwtTokenService;

function transactionFacilityHeaders(User $user): array
{
    $token = app(JwtTokenService::class)->generate($user)['token'];

    return [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer '.$token,
    ];
}

it('lists completed transactions created by the signed in user with summary meta', function () {
    $user = User::factory()->create();
    $sharedUser = User::factory()->create();
    $account = Account::factory()->create(['name' => 'Nomina', 'user_id' => $user->id]);
    $account->users()->attach([$user->id, $sharedUser->id]);

    $parentTransaction = Transaction::factory()->income()->completed()->create([
        'concept' => 'Monthly salary',
        'amount' => 45000,
        'account_id' => $account->id,
        'user_id' => $user->id,
        'scheduled_at' => '2026-06-05',
    ]);
    Transaction::factory()->outcome()->completed()->create([
        'concept' => 'Groceries',
        'amount' => 1200,
        'account_id' => $account->id,
        'user_id' => $user->id,
        'scheduled_at' => '2026-06-10',
    ]);
    Transaction::factory()->income()->completed()->create([
        'concept' => 'Child reimbursement',
        'amount' => 400,
        'account_id' => $account->id,
        'user_id' => $user->id,
        'parent_transaction_id' => $parentTransaction->id,
        'scheduled_at' => '2026-06-12',
    ]);
    Transaction::factory()->income()->completed()->create([
        'concept' => 'Partner salary',
        'amount' => 30000,
        'account_id' => $account->id,
        'user_id' => $sharedUser->id,
        'scheduled_at' => '2026-06-05',
    ]);

    $this
        ->withHeaders(transactionFacilityHeaders($user))
        ->getJson('/api/transactions?start_date=2026-06-01&end_date=2026-06-30&per_page=20')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.account.name', 'Nomina')
        ->assertJsonPath('meta.summary.income_total', 45000.0)
        ->assertJsonPath('meta.summary.outcome_total', 1200.0)
        ->assertJsonPath('meta.summary.balance', 43800.0);
});

it('filters the transaction facility list by concept', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);
    $account->users()->attach($user->id);

    Transaction::factory()->income()->completed()->create([
        'concept' => 'Monthly salary',
        'account_id' => $account->id,
        'user_id' => $user->id,
        'scheduled_at' => '2026-06-05',
    ]);
    Transaction::factory()->outcome()->completed()->create([
        'concept' => 'Groceries',
        'account_id' => $account->id,
        'user_id' => $user->id,
        'scheduled_at' => '2026-06-10',
    ]);

    $this
        ->withHeaders(transactionFacilityHeaders($user))
        ->getJson('/api/transactions?start_date=2026-06-01&end_date=2026-06-30&search=salary')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.concept', 'Monthly salary');
});
