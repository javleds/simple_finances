<?php

use App\Enums\AccountMemberLedgerEntryType;
use App\Enums\TransactionPaymentSource;
use App\Models\Account;
use App\Models\AccountMemberLedgerEntry;
use App\Models\Transaction;
use App\Models\TransactionAllocation;
use App\Models\User;

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
        'account_id' => $account->id,
        'user_id' => $owner->id,
        'amount' => 599.0,
        'paid_by_user_id' => null,
        'payment_source' => null,
    ]);
    $childTransaction = Transaction::factory()->income()->completed()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
        'parent_transaction_id' => $parentTransaction->id,
        'amount' => 0.0,
        'percentage' => 100.0,
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
        ->and((float) $allocation->amount)->toBe(599.0)
        ->and((float) $allocation->percentage)->toBe(100.0)
        ->and($ledgerEntries->pluck('type')->all())->toBe([
            AccountMemberLedgerEntryType::ExpensePaid,
            AccountMemberLedgerEntryType::ExpenseShare,
            AccountMemberLedgerEntryType::SettlementTransfer,
            AccountMemberLedgerEntryType::SettlementTransfer,
        ])
        ->and($ledgerEntries->pluck('amount')->all())->toBe([599.0, -599.0, 599.0, -599.0])
        ->and(round(TransactionAllocation::query()->where('transaction_id', $parentTransaction->id)->sum('amount'), 2))->toBe(599.0);
});

it('preserves account balance contribution from completed legacy child incomes', function () {
    $owner = User::factory()->create();
    $partner = User::factory()->create();
    $account = Account::factory()->create([
        'balance' => -500,
        'user_id' => $owner->id,
    ]);
    $account->users()->sync([
        $owner->id => ['percentage' => 50],
        $partner->id => ['percentage' => 50],
    ]);

    $parentTransaction = Transaction::factory()->outcome()->completed()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
        'amount' => 1000.0,
        'payment_source' => null,
    ]);
    Transaction::factory()->income()->completed()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
        'parent_transaction_id' => $parentTransaction->id,
        'amount' => 500.0,
        'percentage' => 50.0,
    ]);
    Transaction::factory()->income()->pending()->create([
        'account_id' => $account->id,
        'user_id' => $partner->id,
        'parent_transaction_id' => $parentTransaction->id,
        'amount' => 500.0,
        'percentage' => 50.0,
    ]);

    runMigrateLegacySplitTransactionsToMemberLedger();

    expect((float) $account->fresh()->balance)->toBe(-500.0);
});
