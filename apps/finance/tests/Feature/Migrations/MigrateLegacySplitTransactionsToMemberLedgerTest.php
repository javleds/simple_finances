<?php

declare(strict_types=1);

use FinSi\FinanceDomain\Enums\AccountMemberLedgerEntryType;
use FinSi\FinanceDomain\Enums\TransactionPaymentSource;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\AccountMemberLedgerEntry;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Models\TransactionAllocation;
use FinSi\FinanceDomain\Models\User;
use FinSi\FinanceDomain\Money\Decimal;

function runMigrateLegacySplitTransactionsToMemberLedger(): void
{
    $migration = require database_path('migrations/2026_07_19_132817_migrate_legacy_split_transactions_to_member_ledger.php');

    $migration->up();
}

it('normalizes legacy split allocations when child amounts do not match the parent outcome amount', function () {
    $owner = User::factory()->create();
    $account = Account::factory()->create([
        'balance' => 0,
        'user_id' => $owner->id,
    ]);
    $account->users()->attach($owner->id);

    $parentTransaction = Transaction::factory()->outcome()->completed()->create([
        'account_id'      => $account->id,
        'user_id'         => $owner->id,
        'amount'          => '599.00',
        'paid_by_user_id' => null,
        'payment_source'  => null,
    ]);
    $childTransaction = Transaction::factory()->income()->completed()->create([
        'account_id'            => $account->id,
        'user_id'               => $owner->id,
        'parent_transaction_id' => $parentTransaction->id,
        'amount'                => '0.00',
        'percentage'            => '100.00',
    ]);

    runMigrateLegacySplitTransactionsToMemberLedger();

    $allocation = TransactionAllocation::query()
        ->where('transaction_id', $parentTransaction->id)
        ->firstOrFail();
    $ledgerEntries = AccountMemberLedgerEntry::query()
        ->where('transaction_id', $parentTransaction->id)
        ->orderBy('id')
        ->get();

    expect($parentTransaction->fresh()->payment_source)->toBe(TransactionPaymentSource::MemberOutOfPocket)
        ->and($childTransaction->fresh()->legacy_migrated_at)->not->toBeNull()
        ->and($allocation->amount)->toBe('599.00')
        ->and($allocation->percentage)->toBe('100.00')
        ->and($ledgerEntries->pluck('type')->all())->toBe([
            AccountMemberLedgerEntryType::ExpensePaid,
            AccountMemberLedgerEntryType::ExpenseShare,
            AccountMemberLedgerEntryType::SettlementTransfer,
            AccountMemberLedgerEntryType::SettlementTransfer,
        ])
        ->and($ledgerEntries->pluck('amount')->all())->toBe(['599.00', '-599.00', '599.00', '-599.00'])
        ->and(Decimal::normalize(TransactionAllocation::query()->where('transaction_id', $parentTransaction->id)->sum('amount')))->toBe('599.00');
});

it('preserves account balance contribution from completed legacy child incomes', function () {
    $owner = User::factory()->create();
    $partner = User::factory()->create();
    $account = Account::factory()->create([
        'balance' => -500,
        'user_id' => $owner->id,
    ]);
    $account->users()->sync([
        $owner->id   => ['percentage' => 50],
        $partner->id => ['percentage' => 50],
    ]);

    $parentTransaction = Transaction::factory()->outcome()->completed()->create([
        'account_id'     => $account->id,
        'user_id'        => $owner->id,
        'amount'         => '1000.00',
        'payment_source' => null,
    ]);
    Transaction::factory()->income()->completed()->create([
        'account_id'            => $account->id,
        'user_id'               => $owner->id,
        'parent_transaction_id' => $parentTransaction->id,
        'amount'                => '500.00',
        'percentage'            => '50.00',
    ]);
    Transaction::factory()->income()->pending()->create([
        'account_id'            => $account->id,
        'user_id'               => $partner->id,
        'parent_transaction_id' => $parentTransaction->id,
        'amount'                => '500.00',
        'percentage'            => '50.00',
    ]);

    runMigrateLegacySplitTransactionsToMemberLedger();

    expect($account->fresh()->balance)->toBe('-500.00');
});
