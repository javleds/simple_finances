<?php

use App\Enums\Frequency;
use App\Enums\TransactionStatus;
use App\Models\Account;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Auth\JwtTokenService;

function dashboardApiHeaders(User $user): array
{
    $token = app(JwtTokenService::class)->generate($user)['token'];

    return [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer '.$token,
    ];
}

it('serves the dashboard endpoints and completes pending transactions through the api', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create([
        'name' => 'Nomina',
        'balance' => 100,
        'color' => '#6a4d4d',
        'user_id' => $user->id,
    ]);
    $account->users()->attach($user->id);

    $transaction = Transaction::factory()->income()->pending()->create([
        'concept' => 'Pending reimbursement',
        'amount' => 450.5,
        'account_id' => $account->id,
        'user_id' => $user->id,
        'scheduled_at' => '2026-06-06',
    ]);

    Subscription::factory()->create([
        'amount' => 100,
        'frequency_type' => Frequency::Month,
        'frequency_unit' => 1,
        'finished_at' => null,
        'user_id' => $user->id,
    ]);

    $headers = dashboardApiHeaders($user);

    $this->withHeaders($headers)
        ->getJson('/api/dashboard/graph')
        ->assertOk()
        ->assertJsonPath('data.0.account_id', $account->id)
        ->assertJsonPath('data.0.account_name', 'Nomina')
        ->assertJsonPath('data.0.balance', 100)
        ->assertJsonPath('data.0.color', '#6a4d4d');

    $this->withHeaders($headers)
        ->getJson('/api/dashboard/accounts')
        ->assertOk()
        ->assertJsonPath('data.summary.active_accounts', 1)
        ->assertJsonPath('data.summary.shared_accounts', 0)
        ->assertJsonPath('data.summary.pending_total', 450.5)
        ->assertJsonPath('data.pending_actions.0.id', 'tx-'.$transaction->id)
        ->assertJsonPath('data.pending_actions.0.date', '2026-06-06');

    $this->withHeaders($headers)
        ->getJson('/api/dashboard/subscriptions')
        ->assertOk()
        ->assertJsonPath('data.annual_total', 1200)
        ->assertJsonPath('data.subscriptions_count', 1);

    $this->withHeaders($headers)
        ->postJson('/api/batch/transactions', [
            'action' => 'complete',
            'transaction_ids' => ['tx-'.$transaction->id],
        ])
        ->assertOk()
        ->assertJsonPath('data.processed', 1)
        ->assertJsonPath('data.failed', [])
        ->assertJsonPath('data.transaction_ids.0', 'tx-'.$transaction->id);

    expect($transaction->fresh()->status)->toBe(TransactionStatus::Completed);
});
