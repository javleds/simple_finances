<?php

declare(strict_types=1);

use App\Dto\TransactionFormDto;
use App\Services\Transaction\TransactionCreator;
use App\Services\Transaction\TransactionRemover;
use FinSi\FinanceDomain\Enums\TransactionPaymentSource;
use FinSi\FinanceDomain\Enums\TransactionStatus;
use FinSi\FinanceDomain\Enums\TransactionType;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\AccountMemberLedgerEntry;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Models\TransactionAllocation;
use FinSi\FinanceDomain\Models\User;

it('deletes allocations and ledger entries when removing a transaction', function () {
    $owner = User::factory()->create();
    $partner = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);
    $account->users()->sync([
        $owner->id   => ['percentage' => 50],
        $partner->id => ['percentage' => 50],
    ]);
    $this->actingAs($owner);

    $transaction = app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'type'                => TransactionType::Outcome,
        'status'              => TransactionStatus::Completed,
        'concept'             => 'Shared dinner',
        'amount'              => '120.00',
        'account_id'          => $account->id,
        'payment_source'      => TransactionPaymentSource::MemberOutOfPocket,
        'paid_by_user_id'     => $owner->id,
        'split_between_users' => true,
        'user_payments'       => [
            ['user_id' => $owner->id, 'percentage' => 50],
            ['user_id' => $partner->id, 'percentage' => 50],
        ],
        'scheduled_at'        => now(),
        'financial_goal_id'   => null,
    ]));

    $removedSubTransactionIds = app(TransactionRemover::class)->execute($transaction);

    expect($removedSubTransactionIds)->toBe([])
        ->and(Transaction::whereKey($transaction->id)->exists())->toBeFalse()
        ->and(TransactionAllocation::where('transaction_id', $transaction->id)->count())->toBe(0)
        ->and(AccountMemberLedgerEntry::where('transaction_id', $transaction->id)->count())->toBe(0);
});

it('deletes a transaction without allocations', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);
    $account->users()->attach($user->id, ['percentage' => 100]);
    $this->actingAs($user);

    $transaction = app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'type'                => TransactionType::Outcome,
        'status'              => TransactionStatus::Completed,
        'concept'             => 'Solo expense',
        'amount'              => '90.00',
        'account_id'          => $account->id,
        'split_between_users' => false,
        'user_payments'       => [],
        'scheduled_at'        => now(),
        'financial_goal_id'   => null,
    ]));

    app(TransactionRemover::class)->execute($transaction);

    expect(Transaction::whereKey($transaction->id)->exists())->toBeFalse();
});
