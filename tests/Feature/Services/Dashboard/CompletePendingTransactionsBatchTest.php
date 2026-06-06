<?php

use App\Enums\TransactionStatus;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Dashboard\CompletePendingTransactionsBatch;

it('completes pending transactions for the signed in user and reports failures', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $this->actingAs($user);

    $account = Account::factory()->create([
        'balance' => 0,
        'user_id' => $user->id,
    ]);
    $account->users()->attach($user->id);

    $transaction = Transaction::factory()->income()->pending()->create([
        'amount' => 250,
        'account_id' => $account->id,
        'user_id' => $user->id,
    ]);
    $otherTransaction = Transaction::factory()->income()->pending()->create([
        'user_id' => $otherUser->id,
    ]);

    $data = app(CompletePendingTransactionsBatch::class)->execute([
        'tx-'.$transaction->id,
        'tx-'.$otherTransaction->id,
        'invalid',
    ]);

    expect($data['processed'])->toBe(1)
        ->and($data['transaction_ids'])->toBe(['tx-'.$transaction->id])
        ->and($data['failed'])->toHaveCount(2)
        ->and($transaction->fresh()->status)->toBe(TransactionStatus::Completed)
        ->and($account->fresh()->balance)->toBe(250.0);
});
