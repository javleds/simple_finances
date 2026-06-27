<?php

use App\Enums\TransactionStatus;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Transaction\BuildTransactionFacilityQuery;

it('builds the transaction facility query for completed transactions created by the signed in user', function () {
    $user = User::factory()->create();
    $sharedUser = User::factory()->create();
    $this->actingAs($user);

    $account = Account::factory()->create(['user_id' => $user->id]);
    $account->users()->attach([$user->id, $sharedUser->id]);

    $matchingTransaction = Transaction::factory()->income()->completed()->create([
        'concept' => 'Monthly salary',
        'amount' => 45000,
        'account_id' => $account->id,
        'user_id' => $user->id,
        'scheduled_at' => '2026-06-05',
    ]);
    Transaction::factory()->outcome()->pending()->create([
        'concept' => 'Monthly rent',
        'account_id' => $account->id,
        'user_id' => $user->id,
        'scheduled_at' => '2026-06-06',
    ]);
    Transaction::factory()->income()->completed()->create([
        'concept' => 'Monthly salary',
        'account_id' => $account->id,
        'user_id' => $sharedUser->id,
        'scheduled_at' => '2026-06-05',
    ]);
    Transaction::factory()->income()->completed()->create([
        'concept' => 'Monthly salary child',
        'account_id' => $account->id,
        'user_id' => $user->id,
        'parent_transaction_id' => $matchingTransaction->id,
        'scheduled_at' => '2026-06-05',
    ]);
    Transaction::factory()->income()->completed()->create([
        'concept' => 'Monthly salary',
        'account_id' => $account->id,
        'user_id' => $user->id,
        'scheduled_at' => '2026-07-01',
    ]);

    $transactions = app(BuildTransactionFacilityQuery::class)
        ->execute([
            'search' => 'salary',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
        ], $user->id)
        ->get();

    expect($transactions)->toHaveCount(1)
        ->and($transactions->first()->id)->toBe($matchingTransaction->id)
        ->and($transactions->first()->status)->toBe(TransactionStatus::Completed);
});
