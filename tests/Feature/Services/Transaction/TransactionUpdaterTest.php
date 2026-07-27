<?php

use App\Dto\TransactionFormDto;
use App\Enums\TransactionPaymentSource;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountMemberLedgerEntry;
use App\Models\Transaction;
use App\Models\TransactionAllocation;
use App\Models\User;
use App\Services\Transaction\TransactionCreator;
use App\Services\Transaction\TransactionUpdater;

it('creates allocations when enabling split on an existing outcome transaction', function () {
    $owner = User::factory()->create();
    $partner = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id, 'balance' => 1000.0]);
    $account->users()->sync([
        $owner->id => ['percentage' => 50],
        $partner->id => ['percentage' => 50],
    ]);
    $this->actingAs($owner);

    $transaction = app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'type' => TransactionType::Outcome,
        'status' => TransactionStatus::Completed,
        'concept' => 'Shared expense no split',
        'amount' => 180.0,
        'account_id' => $account->id,
        'split_between_users' => false,
        'user_payments' => [],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]));

    $updatedTransaction = app(TransactionUpdater::class)->execute($transaction, TransactionFormDto::fromFormArray([
        'id' => $transaction->id,
        'type' => TransactionType::Outcome,
        'status' => TransactionStatus::Completed,
        'concept' => 'Shared expense no split',
        'amount' => 180.0,
        'account_id' => $account->id,
        'payment_source' => TransactionPaymentSource::MemberOutOfPocket,
        'paid_by_user_id' => $owner->id,
        'split_between_users' => true,
        'user_payments' => [
            ['user_id' => $owner->id, 'percentage' => 40],
            ['user_id' => $partner->id, 'percentage' => 60],
        ],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]));

    $allocations = TransactionAllocation::where('transaction_id', $updatedTransaction->id)->orderBy('id')->get();
    $ledgerEntries = AccountMemberLedgerEntry::where('transaction_id', $updatedTransaction->id)->orderBy('id')->get();

    expect(Transaction::count())->toBe(2)
        ->and($allocations)->toHaveCount(2)
        ->and($allocations->pluck('user_id')->all())->toBe([$owner->id, $partner->id])
        ->and($allocations->pluck('amount')->all())->toBe([72.0, 108.0])
        ->and($ledgerEntries->pluck('type')->map->value->all())->toBe([
            'expense_paid',
            'expense_share',
            'expense_share',
        ])
        ->and($ledgerEntries->pluck('amount')->all())->toBe([180.0, -72.0, -108.0]);
});

it('rebalances allocations and ledger when amount and percentages change', function () {
    $owner = User::factory()->create();
    $partner = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id, 'balance' => 1000.0]);
    $account->users()->sync([
        $owner->id => ['percentage' => 50],
        $partner->id => ['percentage' => 50],
    ]);
    $this->actingAs($owner);

    $transaction = app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'type' => TransactionType::Outcome,
        'status' => TransactionStatus::Completed,
        'concept' => 'Shared expense',
        'amount' => 200.0,
        'account_id' => $account->id,
        'payment_source' => TransactionPaymentSource::MemberOutOfPocket,
        'paid_by_user_id' => $owner->id,
        'split_between_users' => true,
        'user_payments' => [
            ['user_id' => $owner->id, 'percentage' => 25],
            ['user_id' => $partner->id, 'percentage' => 75],
        ],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]));

    app(TransactionUpdater::class)->execute($transaction, TransactionFormDto::fromFormArray([
        'id' => $transaction->id,
        'type' => TransactionType::Outcome,
        'status' => TransactionStatus::Completed,
        'concept' => 'Shared expense updated',
        'amount' => 300.0,
        'account_id' => $account->id,
        'payment_source' => TransactionPaymentSource::MemberOutOfPocket,
        'paid_by_user_id' => $owner->id,
        'split_between_users' => true,
        'user_payments' => [
            ['user_id' => $owner->id, 'percentage' => 30],
            ['user_id' => $partner->id, 'percentage' => 70],
        ],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]));

    $allocations = TransactionAllocation::where('transaction_id', $transaction->id)->orderBy('id')->get();
    $ledgerEntries = AccountMemberLedgerEntry::where('transaction_id', $transaction->id)->orderBy('id')->get();

    expect($transaction->fresh()->amount)->toBe(300.0)
        ->and($allocations->pluck('amount')->all())->toBe([90.0, 210.0])
        ->and($allocations->pluck('percentage')->all())->toBe([30.0, 70.0])
        ->and($ledgerEntries->pluck('amount')->all())->toBe([300.0, -90.0, -210.0]);
});

it('removes allocations and ledger when changing an outcome to income', function () {
    $owner = User::factory()->create();
    $partner = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id, 'balance' => 1000.0]);
    $account->users()->sync([
        $owner->id => ['percentage' => 50],
        $partner->id => ['percentage' => 50],
    ]);
    $this->actingAs($owner);

    $transaction = app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'type' => TransactionType::Outcome,
        'status' => TransactionStatus::Completed,
        'concept' => 'Outcome to income',
        'amount' => 100.0,
        'account_id' => $account->id,
        'payment_source' => TransactionPaymentSource::MemberOutOfPocket,
        'paid_by_user_id' => $owner->id,
        'split_between_users' => true,
        'user_payments' => [
            ['user_id' => $owner->id, 'percentage' => 50],
            ['user_id' => $partner->id, 'percentage' => 50],
        ],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]));

    app(TransactionUpdater::class)->execute($transaction, TransactionFormDto::fromFormArray([
        'id' => $transaction->id,
        'type' => TransactionType::Income,
        'status' => TransactionStatus::Completed,
        'concept' => 'Outcome to income',
        'amount' => 100.0,
        'account_id' => $account->id,
        'custodian_user_id' => $owner->id,
        'split_between_users' => false,
        'user_payments' => [],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]));

    expect(TransactionAllocation::where('transaction_id', $transaction->id)->count())->toBe(0)
        ->and(AccountMemberLedgerEntry::where('transaction_id', $transaction->id)->pluck('type')->map->value->all())->toBe(['income_custody'])
        ->and($transaction->fresh()->type)->toBe(TransactionType::Income);
});
