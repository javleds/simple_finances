<?php

use App\Enums\AccountMemberLedgerEntryType;
use App\Enums\TransactionPaymentSource;
use App\Models\Account;
use App\Models\AccountMemberLedgerEntry;
use App\Models\Transaction;
use App\Models\TransactionAllocation;
use App\Models\User;

function runNormalizeKnownAccountMemberLedgerFromLegacyData(): void
{
    $migration = require database_path('migrations/2026_07_19_212108_normalize_known_account_member_ledger_from_legacy_data.php');

    $migration->up();
}

it('converts known Mini US medical expenses into reimbursements owed to Divanny', function () {
    $javier = User::factory()->create(['id' => 1, 'name' => 'Javier Ledezma']);
    $divanny = User::factory()->create(['id' => 3, 'name' => 'Divanny Marisol']);
    $account = Account::factory()->create([
        'id' => 20,
        'name' => 'Mini US',
        'balance' => 13470.50,
        'user_id' => $javier->id,
    ]);
    $account->users()->sync([
        $javier->id => ['percentage' => 50],
        $divanny->id => ['percentage' => 50],
    ]);
    AccountMemberLedgerEntry::query()->create([
        'account_id' => $account->id,
        'user_id' => $javier->id,
        'type' => AccountMemberLedgerEntryType::IncomeCustody,
        'amount' => 16070.50,
        'description' => 'Existing custody',
        'occurred_at' => now(),
    ]);
    AccountMemberLedgerEntry::query()->create([
        'account_id' => $account->id,
        'user_id' => $divanny->id,
        'type' => AccountMemberLedgerEntryType::AccountFundExpense,
        'amount' => -2600.00,
        'description' => 'Existing expenses',
        'occurred_at' => now(),
    ]);
    $juneExpense = Transaction::factory()->outcome()->completed()->create([
        'id' => 1761,
        'account_id' => $account->id,
        'user_id' => $divanny->id,
        'paid_by_user_id' => $divanny->id,
        'payment_source' => TransactionPaymentSource::AccountFund,
        'concept' => 'Dr. Abraham 2-junio',
        'amount' => 1100.00,
    ]);
    $julyExpense = Transaction::factory()->outcome()->completed()->create([
        'id' => 1762,
        'account_id' => $account->id,
        'user_id' => $divanny->id,
        'paid_by_user_id' => $divanny->id,
        'payment_source' => TransactionPaymentSource::AccountFund,
        'concept' => 'Dr. Abraham 2-jul',
        'amount' => 1500.00,
    ]);

    runNormalizeKnownAccountMemberLedgerFromLegacyData();

    $ledger = AccountMemberLedgerEntry::query()
        ->where('account_id', $account->id)
        ->selectRaw("
            user_id,
            ROUND(SUM(CASE WHEN type IN ('income_custody','account_fund_expense','internal_transfer','manual_adjustment') THEN amount ELSE 0 END), 2) custody_amount,
            ROUND(SUM(CASE WHEN type IN ('expense_paid','expense_share','settlement_transfer','legacy_settlement') THEN amount ELSE 0 END), 2) settlement_amount
        ")
        ->groupBy('user_id')
        ->orderBy('user_id')
        ->get();

    expect($juneExpense->fresh()->payment_source)->toBe(TransactionPaymentSource::MemberOutOfPocket)
        ->and($julyExpense->fresh()->payment_source)->toBe(TransactionPaymentSource::MemberOutOfPocket)
        ->and(TransactionAllocation::query()->where('transaction_id', $juneExpense->id)->firstOrFail()->user_id)->toBe($javier->id)
        ->and(TransactionAllocation::query()->where('transaction_id', $julyExpense->id)->firstOrFail()->user_id)->toBe($javier->id)
        ->and($ledger->pluck('custody_amount', 'user_id')->all())->toBe([
            $javier->id => 13470.5,
            $divanny->id => 0.0,
        ])
        ->and($ledger->pluck('settlement_amount', 'user_id')->all())->toBe([
            $javier->id => -2600.0,
            $divanny->id => 2600.0,
        ]);
});

it('normalizes known current account custody and Nuestros gastos settlement totals', function () {
    $javier = User::factory()->create(['id' => 1, 'name' => 'Javier Ledezma']);
    $divanny = User::factory()->create(['id' => 3, 'name' => 'Divanny Marisol']);
    $homeSweetHome = Account::factory()->create([
        'id' => 10,
        'name' => 'Home sweet home',
        'balance' => 163511.85,
        'user_id' => $javier->id,
    ]);
    $nuestrosGastos = Account::factory()->create([
        'id' => 19,
        'name' => 'Nuestros gastos',
        'balance' => -5534.96,
        'user_id' => $javier->id,
    ]);

    foreach ([$homeSweetHome, $nuestrosGastos] as $account) {
        $account->users()->sync([
            $javier->id => ['percentage' => 50],
            $divanny->id => ['percentage' => 50],
        ]);
    }

    AccountMemberLedgerEntry::query()->create([
        'account_id' => $homeSweetHome->id,
        'user_id' => $javier->id,
        'type' => AccountMemberLedgerEntryType::IncomeCustody,
        'amount' => 408688.00,
        'description' => 'Existing custody',
        'occurred_at' => now(),
    ]);
    AccountMemberLedgerEntry::query()->create([
        'account_id' => $homeSweetHome->id,
        'user_id' => $divanny->id,
        'type' => AccountMemberLedgerEntryType::AccountFundExpense,
        'amount' => -245176.15,
        'description' => 'Existing expenses',
        'occurred_at' => now(),
    ]);
    AccountMemberLedgerEntry::query()->create([
        'account_id' => $nuestrosGastos->id,
        'user_id' => $javier->id,
        'type' => AccountMemberLedgerEntryType::IncomeCustody,
        'amount' => -6143.14,
        'description' => 'Existing custody',
        'occurred_at' => now(),
    ]);
    AccountMemberLedgerEntry::query()->create([
        'account_id' => $nuestrosGastos->id,
        'user_id' => $divanny->id,
        'type' => AccountMemberLedgerEntryType::AccountFundExpense,
        'amount' => 6143.14,
        'description' => 'Existing expenses',
        'occurred_at' => now(),
    ]);
    AccountMemberLedgerEntry::query()->create([
        'account_id' => $nuestrosGastos->id,
        'user_id' => $javier->id,
        'type' => AccountMemberLedgerEntryType::ExpensePaid,
        'amount' => 5535.00,
        'description' => 'Existing settlement',
        'occurred_at' => now(),
    ]);
    AccountMemberLedgerEntry::query()->create([
        'account_id' => $nuestrosGastos->id,
        'user_id' => $divanny->id,
        'type' => AccountMemberLedgerEntryType::ExpenseShare,
        'amount' => -5535.00,
        'description' => 'Existing settlement',
        'occurred_at' => now(),
    ]);

    runNormalizeKnownAccountMemberLedgerFromLegacyData();

    $summary = AccountMemberLedgerEntry::query()
        ->whereIn('account_id', [$homeSweetHome->id, $nuestrosGastos->id])
        ->selectRaw("
            account_id,
            user_id,
            ROUND(SUM(CASE WHEN type IN ('income_custody','account_fund_expense','internal_transfer','manual_adjustment') THEN amount ELSE 0 END), 2) custody_amount,
            ROUND(SUM(CASE WHEN type IN ('expense_paid','expense_share','settlement_transfer','legacy_settlement') THEN amount ELSE 0 END), 2) settlement_amount
        ")
        ->groupBy('account_id', 'user_id')
        ->orderBy('account_id')
        ->orderBy('user_id')
        ->get()
        ->mapWithKeys(fn (AccountMemberLedgerEntry $entry): array => [
            "{$entry->account_id}:{$entry->user_id}" => [
                'custody' => $entry->custody_amount,
                'settlement' => $entry->settlement_amount,
            ],
        ]);

    expect($summary->all())->toBe([
        '10:1' => ['custody' => 0.0, 'settlement' => 0.0],
        '10:3' => ['custody' => 163511.85, 'settlement' => 0.0],
        '19:1' => ['custody' => 0.0, 'settlement' => 5534.96],
        '19:3' => ['custody' => 0.0, 'settlement' => -5534.96],
    ]);
});
