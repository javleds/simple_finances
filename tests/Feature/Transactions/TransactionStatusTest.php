<?php

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;

it('sets completed as default transaction status', function () {
    $transaction = Transaction::factory()->create();

    expect($transaction->status)->toBe(TransactionStatus::Completed);
});

it('excludes pending transactions from balance calculations', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);

    Transaction::factory()->for($user)->for($account)->create([
        'type' => TransactionType::Income,
        'amount' => 150.0,
        'status' => TransactionStatus::Completed,
    ]);

    Transaction::factory()->for($user)->for($account)->create([
        'type' => TransactionType::Outcome,
        'amount' => 40.0,
        'status' => TransactionStatus::Completed,
    ]);

    Transaction::factory()->for($user)->for($account)->pending()->create([
        'type' => TransactionType::Outcome,
        'amount' => 25.0,
    ]);

    $balance = $account->updateBalance();

    expect($balance)->toBe(110.0);
});
