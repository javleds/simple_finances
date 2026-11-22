<?php

declare(strict_types=1);

use App\Dto\TransactionFormDto;
use App\Services\Transaction\TransactionCreator;
use App\Services\Transaction\TransactionUpdater;
use FinSi\FinanceDomain\Enums\TransactionPaymentSource;
use FinSi\FinanceDomain\Enums\TransactionStatus;
use FinSi\FinanceDomain\Enums\TransactionType;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\AccountMemberLedgerEntry;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Models\TransactionAllocation;
use FinSi\FinanceDomain\Models\User;

it('creates allocations when enabling split on an existing outcome transaction', function () {
    $owner = User::factory()->create();
    $partner = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id, 'balance' => '1000.00']);
    $account->users()->sync([
        $owner->id   => ['percentage' => 50],
        $partner->id => ['percentage' => 50],
    ]);
    $this->actingAs($owner);

    $transaction = app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'type'                => TransactionType::Outcome,
        'status'              => TransactionStatus::Completed,
        'concept'             => 'Shared expense no split',
        'amount'              => '180.00',
        'account_id'          => $account->id,
        'split_between_users' => false,
        'user_payments'       => [],
        'scheduled_at'        => now(),
        'financial_goal_id'   => null,
    ]));

    $updatedTransaction = app(TransactionUpdater::class)->execute($transaction, TransactionFormDto::fromFormArray([
        'id'                  => $transaction->id,
        'type'                => TransactionType::Outcome,
        'status'              => TransactionStatus::Completed,
        'concept'             => 'Shared expense no split',
        'amount'              => '180.00',
        'account_id'          => $account->id,
        'payment_source'      => TransactionPaymentSource::MemberOutOfPocket,
        'paid_by_user_id'     => $owner->id,
        'split_between_users' => true,
        'user_payments'       => [
            ['user_id' => $owner->id, 'percentage' => 40],
            ['user_id' => $partner->id, 'percentage' => 60],
        ],
        'scheduled_at'        => now(),
        'financial_goal_id'   => null,
    ]));

    $allocations = TransactionAllocation::where('transaction_id', $updatedTransaction->id)->orderBy('id')->get();
    $ledgerEntries = AccountMemberLedgerEntry::where('transaction_id', $updatedTransaction->id)->orderBy('id')->get();

    expect(Transaction::count())->toBe(2)
        ->and($allocations)->toHaveCount(2)
        ->and($allocations->pluck('user_id')->all())->toBe([$owner->id, $partner->id])
        ->and($allocations->pluck('amount')->all())->toBe(['72.00', '108.00'])
        ->and($ledgerEntries->pluck('type')->map->value->all())->toBe([
            'expense_paid',
            'expense_share',
            'expense_share',
        ])
        ->and($ledgerEntries->pluck('amount')->all())->toBe(['180.00', '-72.00', '-108.00']);
});

it('rebalances allocations and ledger when amount and percentages change', function () {
    $owner = User::factory()->create();
    $partner = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id, 'balance' => '1000.00']);
    $account->users()->sync([
        $owner->id   => ['percentage' => 50],
        $partner->id => ['percentage' => 50],
    ]);
    $this->actingAs($owner);

    $transaction = app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'type'                => TransactionType::Outcome,
        'status'              => TransactionStatus::Completed,
        'concept'             => 'Shared expense',
        'amount'              => '200.00',
        'account_id'          => $account->id,
        'payment_source'      => TransactionPaymentSource::MemberOutOfPocket,
        'paid_by_user_id'     => $owner->id,
        'split_between_users' => true,
        'user_payments'       => [
            ['user_id' => $owner->id, 'percentage' => 25],
            ['user_id' => $partner->id, 'percentage' => 75],
        ],
        'scheduled_at'        => now(),
        'financial_goal_id'   => null,
    ]));

    app(TransactionUpdater::class)->execute($transaction, TransactionFormDto::fromFormArray([
        'id'                  => $transaction->id,
        'type'                => TransactionType::Outcome,
        'status'              => TransactionStatus::Completed,
        'concept'             => 'Shared expense updated',
        'amount'              => '300.00',
        'account_id'          => $account->id,
        'payment_source'      => TransactionPaymentSource::MemberOutOfPocket,
        'paid_by_user_id'     => $owner->id,
        'split_between_users' => true,
        'user_payments'       => [
            ['user_id' => $owner->id, 'percentage' => 30],
            ['user_id' => $partner->id, 'percentage' => 70],
        ],
        'scheduled_at'        => now(),
        'financial_goal_id'   => null,
    ]));

    $allocations = TransactionAllocation::where('transaction_id', $transaction->id)->orderBy('id')->get();
    $ledgerEntries = AccountMemberLedgerEntry::where('transaction_id', $transaction->id)->orderBy('id')->get();

    expect($transaction->fresh()->amount)->toBe('300.00')
        ->and($allocations->pluck('amount')->all())->toBe(['90.00', '210.00'])
        ->and($allocations->pluck('percentage')->all())->toBe(['30.00', '70.00'])
        ->and($ledgerEntries->pluck('amount')->all())->toBe(['300.00', '-90.00', '-210.00']);
});

it('removes allocations and ledger when changing an outcome to income', function () {
    $owner = User::factory()->create();
    $partner = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id, 'balance' => '1000.00']);
    $account->users()->sync([
        $owner->id   => ['percentage' => 50],
        $partner->id => ['percentage' => 50],
    ]);
    $this->actingAs($owner);

    $transaction = app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'type'                => TransactionType::Outcome,
        'status'              => TransactionStatus::Completed,
        'concept'             => 'Outcome to income',
        'amount'              => '100.00',
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

    app(TransactionUpdater::class)->execute($transaction, TransactionFormDto::fromFormArray([
        'id'                  => $transaction->id,
        'type'                => TransactionType::Income,
        'status'              => TransactionStatus::Completed,
        'concept'             => 'Outcome to income',
        'amount'              => '100.00',
        'account_id'          => $account->id,
        'custodian_user_id'   => $owner->id,
        'split_between_users' => false,
        'user_payments'       => [],
        'scheduled_at'        => now(),
        'financial_goal_id'   => null,
    ]));

    expect(TransactionAllocation::where('transaction_id', $transaction->id)->count())->toBe(0)
        ->and(AccountMemberLedgerEntry::where('transaction_id', $transaction->id)->pluck('type')->map->value->all())->toBe(['income_custody'])
        ->and($transaction->fresh()->type)->toBe(TransactionType::Income);
});

it('removes split allocations when updating an out of pocket expense to a single responsible member', function () {
    $owner = User::factory()->create();
    $partner = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id, 'balance' => '3000.00']);
    $account->users()->sync([
        $owner->id   => ['percentage' => 50],
        $partner->id => ['percentage' => 50],
    ]);
    $this->actingAs($owner);

    $transaction = app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'type'                => TransactionType::Outcome,
        'status'              => TransactionStatus::Completed,
        'concept'             => 'Split by mistake',
        'amount'              => '2200.00',
        'account_id'          => $account->id,
        'payment_source'      => TransactionPaymentSource::MemberOutOfPocket,
        'paid_by_user_id'     => $partner->id,
        'split_between_users' => true,
        'user_payments'       => [
            ['user_id' => $owner->id, 'percentage' => 50],
            ['user_id' => $partner->id, 'percentage' => 50],
        ],
        'scheduled_at'        => now(),
        'financial_goal_id'   => null,
    ]));

    app(TransactionUpdater::class)->execute($transaction, TransactionFormDto::fromFormArray([
        'id'                  => $transaction->id,
        'type'                => TransactionType::Outcome,
        'status'              => TransactionStatus::Completed,
        'concept'             => 'Split by mistake',
        'amount'              => '2200.00',
        'account_id'          => $account->id,
        'payment_source'      => TransactionPaymentSource::MemberOutOfPocket,
        'paid_by_user_id'     => $partner->id,
        'split_between_users' => false,
        'user_payments'       => [],
        'scheduled_at'        => now(),
        'financial_goal_id'   => null,
    ]));

    $ledgerEntries = AccountMemberLedgerEntry::query()
        ->where('transaction_id', $transaction->id)
        ->orderBy('id')
        ->get();

    expect(TransactionAllocation::query()->where('transaction_id', $transaction->id)->count())->toBe(0)
        ->and(Transaction::query()->where('parent_transaction_id', $transaction->id)->exists())->toBeFalse()
        ->and($ledgerEntries->pluck('user_id')->all())->toBe([$partner->id, $owner->id])
        ->and($ledgerEntries->pluck('type')->map->value->all())->toBe(['expense_paid', 'expense_share'])
        ->and($ledgerEntries->pluck('amount')->all())->toBe(['2200.00', '-2200.00']);
});
