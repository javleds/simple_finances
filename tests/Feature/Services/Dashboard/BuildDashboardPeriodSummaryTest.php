<?php

use App\Enums\TransactionStatus;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Dashboard\BuildDashboardPeriodSummary;

it('summarizes completed transaction totals inside the selected period', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $this->actingAs($user);

    $account = Account::factory()->create(['user_id' => $user->id]);
    $account->users()->attach($user->id);
    $deletedAccount = Account::factory()->create(['user_id' => $user->id]);
    $deletedAccount->users()->attach($user->id);

    $otherAccount = Account::factory()->create(['user_id' => $otherUser->id]);
    $otherAccount->users()->attach($otherUser->id);

    Transaction::factory()->income()->completed()->create([
        'amount' => 50000,
        'account_id' => $account->id,
        'user_id' => $user->id,
        'scheduled_at' => '2026-06-01',
    ]);
    $parentTransaction = Transaction::factory()->income()->completed()->create([
        'amount' => 45000,
        'account_id' => $account->id,
        'user_id' => $user->id,
        'scheduled_at' => '2026-06-05',
    ]);
    Transaction::factory()->income()->completed()->create([
        'amount' => 700,
        'account_id' => $account->id,
        'user_id' => $user->id,
        'parent_transaction_id' => $parentTransaction->id,
        'scheduled_at' => '2026-06-05',
    ]);
    Transaction::factory()->outcome()->completed()->create([
        'amount' => 1250.75,
        'account_id' => $account->id,
        'user_id' => $user->id,
        'scheduled_at' => '2026-06-30 23:59:00',
    ]);
    Transaction::factory()->outcome()->pending()->create([
        'amount' => 900,
        'account_id' => $account->id,
        'user_id' => $user->id,
        'scheduled_at' => '2026-06-10',
    ]);
    Transaction::factory()->income()->completed()->create([
        'amount' => 5000,
        'account_id' => $account->id,
        'user_id' => $user->id,
        'scheduled_at' => '2026-07-01',
    ]);
    Transaction::factory()->income()->completed()->create([
        'amount' => 3000,
        'account_id' => $otherAccount->id,
        'user_id' => $otherUser->id,
        'scheduled_at' => '2026-06-15',
    ]);
    Transaction::factory()->income()->completed()->create([
        'amount' => 90000,
        'account_id' => $deletedAccount->id,
        'user_id' => $user->id,
        'scheduled_at' => '2026-06-15',
    ]);
    $deletedAccount->delete();

    $summary = app(BuildDashboardPeriodSummary::class)->execute('2026-06-05', '2026-06-30');

    expect($summary)->toBe([
        'period' => [
            'start_date' => '2026-06-05',
            'end_date' => '2026-06-30',
        ],
        'income_total' => 45000.0,
        'outcome_total' => 1250.75,
        'balance' => 43749.25,
    ]);
});

it('returns a negative balance when outcomes are greater than incomes', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $account = Account::factory()->create(['user_id' => $user->id]);
    $account->users()->attach($user->id);

    Transaction::factory()->income()->completed()->create([
        'amount' => 100,
        'account_id' => $account->id,
        'user_id' => $user->id,
        'scheduled_at' => '2026-06-05',
    ]);
    Transaction::factory()->outcome()->completed()->create([
        'amount' => 150,
        'account_id' => $account->id,
        'user_id' => $user->id,
        'scheduled_at' => '2026-06-06',
    ]);

    $summary = app(BuildDashboardPeriodSummary::class)->execute('2026-06-01', '2026-06-30');

    expect($summary['income_total'])->toBe(100.0)
        ->and($summary['outcome_total'])->toBe(150.0)
        ->and($summary['balance'])->toBe(-50.0);
});
