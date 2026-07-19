<?php

use App\Enums\AccountMemberLedgerEntryType;
use App\Enums\TransactionPaymentSource;
use App\Enums\TransactionStatus;
use App\Models\Account;
use App\Models\AccountMemberLedgerEntry;
use App\Models\Transaction;
use App\Models\TransactionAllocation;
use App\Models\User;

function runCompleteActivePendingTransactionsMigration(): void
{
    $migration = require database_path('migrations/2026_07_19_165913_complete_active_pending_transactions.php');

    $migration->up();
}

it('converts pending income transactions into completed custody entries', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create([
        'balance' => 0,
        'user_id' => $user->id,
    ]);
    $account->users()->attach($user->id);

    $transaction = Transaction::factory()->income()->pending()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
        'amount' => 500.0,
        'custodian_user_id' => null,
    ]);

    runCompleteActivePendingTransactionsMigration();

    $ledgerEntry = AccountMemberLedgerEntry::query()
        ->where('transaction_id', $transaction->id)
        ->firstOrFail();

    expect($transaction->fresh()->status)->toBe(TransactionStatus::Completed)
        ->and($transaction->fresh()->custodian_user_id)->toBe($user->id)
        ->and($ledgerEntry->type)->toBe(AccountMemberLedgerEntryType::IncomeCustody)
        ->and((float) $ledgerEntry->amount)->toBe(500.0)
        ->and((float) $account->fresh()->balance)->toBe(500.0);
});

it('converts pending out of pocket outcomes into completed settlement entries', function () {
    $owner = User::factory()->create();
    $partner = User::factory()->create();
    $account = Account::factory()->create([
        'balance' => 0,
        'user_id' => $owner->id,
    ]);
    $account->users()->sync([
        $owner->id => ['percentage' => 50],
        $partner->id => ['percentage' => 50],
    ]);

    $transaction = Transaction::factory()->outcome()->pending()->create([
        'account_id' => $account->id,
        'user_id' => $partner->id,
        'paid_by_user_id' => $partner->id,
        'payment_source' => TransactionPaymentSource::MemberOutOfPocket,
        'amount' => 1000.0,
    ]);
    TransactionAllocation::query()->create([
        'transaction_id' => $transaction->id,
        'user_id' => $owner->id,
        'percentage' => 50,
        'amount' => 500,
    ]);
    TransactionAllocation::query()->create([
        'transaction_id' => $transaction->id,
        'user_id' => $partner->id,
        'percentage' => 50,
        'amount' => 500,
    ]);

    runCompleteActivePendingTransactionsMigration();

    $ledgerEntries = AccountMemberLedgerEntry::query()
        ->where('transaction_id', $transaction->id)
        ->orderBy('id')
        ->get();

    expect($transaction->fresh()->status)->toBe(TransactionStatus::Completed)
        ->and($ledgerEntries->pluck('type')->all())->toBe([
            AccountMemberLedgerEntryType::ExpensePaid,
            AccountMemberLedgerEntryType::ExpenseShare,
            AccountMemberLedgerEntryType::ExpenseShare,
        ])
        ->and($ledgerEntries->pluck('amount')->all())->toBe([1000.0, -500.0, -500.0])
        ->and((float) $account->fresh()->balance)->toBe(-1000.0);
});

it('converts pending legacy split outcomes without turning child pending incomes into account income', function () {
    $owner = User::factory()->create();
    $partner = User::factory()->create();
    $account = Account::factory()->create([
        'balance' => 0,
        'user_id' => $owner->id,
    ]);
    $account->users()->sync([
        $owner->id => ['percentage' => 50],
        $partner->id => ['percentage' => 50],
    ]);

    $parentTransaction = Transaction::factory()->outcome()->pending()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
        'amount' => 1000.0,
        'paid_by_user_id' => null,
        'payment_source' => null,
    ]);
    $ownerShare = Transaction::factory()->income()->completed()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
        'parent_transaction_id' => $parentTransaction->id,
        'amount' => 500.0,
        'percentage' => 50,
    ]);
    $partnerShare = Transaction::factory()->income()->pending()->create([
        'account_id' => $account->id,
        'user_id' => $partner->id,
        'parent_transaction_id' => $parentTransaction->id,
        'amount' => 500.0,
        'percentage' => 50,
    ]);

    runCompleteActivePendingTransactionsMigration();

    $ledgerEntries = AccountMemberLedgerEntry::query()
        ->where('transaction_id', $parentTransaction->id)
        ->orderBy('id')
        ->get();
    $allocations = TransactionAllocation::query()
        ->where('transaction_id', $parentTransaction->id)
        ->orderBy('user_id')
        ->get();

    expect($parentTransaction->fresh()->status)->toBe(TransactionStatus::Completed)
        ->and($parentTransaction->fresh()->payment_source)->toBe(TransactionPaymentSource::MemberOutOfPocket)
        ->and($ownerShare->fresh()->legacy_migrated_at)->not->toBeNull()
        ->and($partnerShare->fresh()->legacy_migrated_at)->not->toBeNull()
        ->and($partnerShare->fresh()->status)->toBe(TransactionStatus::Pending)
        ->and($allocations->pluck('amount')->all())->toBe([500.0, 500.0])
        ->and($ledgerEntries->pluck('type')->all())->toBe([
            AccountMemberLedgerEntryType::ExpensePaid,
            AccountMemberLedgerEntryType::ExpenseShare,
            AccountMemberLedgerEntryType::SettlementTransfer,
            AccountMemberLedgerEntryType::SettlementTransfer,
            AccountMemberLedgerEntryType::ExpenseShare,
        ])
        ->and((float) $account->fresh()->balance)->toBe(-1000.0);
});

it('recalculates credit card account fields after completing pending transactions', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create([
        'balance' => 0,
        'credit_card' => true,
        'credit_line' => 10000,
        'available_credit' => 10000,
        'spent' => 0,
        'next_cutoff_date' => now()->addDays(5),
        'user_id' => $user->id,
    ]);
    $account->users()->attach($user->id);

    Transaction::factory()->outcome()->pending()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
        'paid_by_user_id' => $user->id,
        'payment_source' => TransactionPaymentSource::AccountFund,
        'amount' => 1500.0,
        'scheduled_at' => now()->addDay(),
    ]);

    Transaction::factory()->outcome()->completed()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
        'paid_by_user_id' => $user->id,
        'payment_source' => TransactionPaymentSource::AccountFund,
        'amount' => 500.0,
        'scheduled_at' => now()->addDays(10),
    ]);

    runCompleteActivePendingTransactionsMigration();

    $account->refresh();

    expect((float) $account->balance)->toBe(-1500.0)
        ->and((float) $account->spent)->toBe(-2000.0)
        ->and((float) $account->available_credit)->toBe(8000.0);
});
