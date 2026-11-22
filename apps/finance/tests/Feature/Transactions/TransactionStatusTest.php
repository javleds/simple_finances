<?php

declare(strict_types=1);

use FinSi\FinanceDomain\Enums\TransactionStatus;
use FinSi\FinanceDomain\Enums\TransactionType;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Models\User;

it('sets completed as default transaction status', function () {
    $transaction = Transaction::factory()->create();

    expect($transaction->status)->toBe(TransactionStatus::Completed);
});

it('excludes pending transactions from balance calculations', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);

    Transaction::factory()->for($user)->for($account)->create([
        'type'   => TransactionType::Income,
        'amount' => '150.00',
        'status' => TransactionStatus::Completed,
    ]);

    Transaction::factory()->for($user)->for($account)->create([
        'type'   => TransactionType::Outcome,
        'amount' => '40.00',
        'status' => TransactionStatus::Completed,
    ]);

    Transaction::factory()->for($user)->for($account)->pending()->create([
        'type'   => TransactionType::Outcome,
        'amount' => '25.00',
    ]);

    $balance = app(App\Services\Accounts\RecalculateAccountBalance::class)->execute($account)->balance;

    expect($balance)->toBe('110.00');
});
