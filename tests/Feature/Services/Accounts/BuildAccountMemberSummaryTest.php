<?php

use App\Dto\TransactionFormDto;
use App\Enums\AccountMemberLedgerEntryType;
use App\Enums\TransactionPaymentSource;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountMemberLedgerEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounts\BuildAccountMemberSummary;
use App\Services\Accounts\RegisterAccountMemberTransfer;
use App\Services\Transaction\HydrateCurrentUserPendingReimbursements;
use App\Services\Transaction\TransactionCreator;
use Carbon\CarbonImmutable;

it('summarizes out of pocket shared expenses as reimbursements between members', function () {
    $owner = User::factory()->create(['name' => 'Owner']);
    $partner = User::factory()->create(['name' => 'Partner']);
    $account = Account::factory()->create(['user_id' => $owner->id, 'balance' => 0.0]);
    $account->users()->sync([
        $owner->id => ['percentage' => 50],
        $partner->id => ['percentage' => 50],
    ]);
    $this->actingAs($partner);

    $transaction = app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'type' => TransactionType::Outcome,
        'status' => TransactionStatus::Completed,
        'concept' => 'Travel dinner',
        'amount' => 1000.0,
        'account_id' => $account->id,
        'payment_source' => TransactionPaymentSource::MemberOutOfPocket,
        'paid_by_user_id' => $partner->id,
        'split_between_users' => true,
        'user_payments' => [
            ['user_id' => $owner->id, 'percentage' => 50],
            ['user_id' => $partner->id, 'percentage' => 50],
        ],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]));

    $summary = app(BuildAccountMemberSummary::class)->execute($account);

    expect($summary['settlements_by_user'])->toMatchArray([
        ['user_id' => $owner->id, 'user_name' => 'Owner', 'amount' => -500.0],
        ['user_id' => $partner->id, 'user_name' => 'Partner', 'amount' => 500.0],
    ])
        ->and($summary['pending_reimbursements'])->toBe([
            [
            'from_user_id' => $owner->id,
            'from_user_name' => 'Owner',
            'to_user_id' => $partner->id,
            'to_user_name' => 'Partner',
            'amount' => 500.0,
            'action_type' => 'user_to_user',
            'items' => [
                [
                    'transaction_id' => (string) $transaction->id,
                    'concept' => 'Travel dinner',
                    'amount' => 500.0,
                    'occurred_at' => $transaction->scheduled_at->toDateString(),
                ],
            ],
            ],
        ]);
});

it('keeps opposite transaction debts actionable instead of hiding them behind account netting', function () {
    $javier = User::factory()->create(['name' => 'Javier']);
    $divanny = User::factory()->create(['name' => 'Divanny']);
    $account = Account::factory()->create(['user_id' => $javier->id]);
    $account->users()->sync([
        $javier->id => ['percentage' => 50],
        $divanny->id => ['percentage' => 50],
    ]);

    $filos = Transaction::factory()
        ->outcome()
        ->completed()
        ->create([
            'account_id' => $account->id,
            'user_id' => $javier->id,
            'paid_by_user_id' => $divanny->id,
            'concept' => 'Filos',
            'amount' => 200.0,
            'scheduled_at' => CarbonImmutable::parse('2026-07-31'),
        ]);
    $groceries = Transaction::factory()
        ->outcome()
        ->completed()
        ->create([
            'account_id' => $account->id,
            'user_id' => $javier->id,
            'paid_by_user_id' => $javier->id,
            'concept' => 'Groceries',
            'amount' => 500.0,
            'scheduled_at' => CarbonImmutable::parse('2026-08-01'),
        ]);

    AccountMemberLedgerEntry::query()->create([
        'account_id' => $account->id,
        'user_id' => $divanny->id,
        'transaction_id' => $filos->id,
        'type' => AccountMemberLedgerEntryType::ExpensePaid,
        'amount' => 200.0,
        'description' => 'Filos',
        'occurred_at' => $filos->scheduled_at,
    ]);
    AccountMemberLedgerEntry::query()->create([
        'account_id' => $account->id,
        'user_id' => $javier->id,
        'transaction_id' => $filos->id,
        'related_user_id' => $divanny->id,
        'type' => AccountMemberLedgerEntryType::ExpenseShare,
        'amount' => -100.0,
        'description' => 'Filos',
        'occurred_at' => $filos->scheduled_at,
    ]);
    AccountMemberLedgerEntry::query()->create([
        'account_id' => $account->id,
        'user_id' => $divanny->id,
        'transaction_id' => $filos->id,
        'related_user_id' => $divanny->id,
        'type' => AccountMemberLedgerEntryType::ExpenseShare,
        'amount' => -100.0,
        'description' => 'Filos',
        'occurred_at' => $filos->scheduled_at,
    ]);

    AccountMemberLedgerEntry::query()->create([
        'account_id' => $account->id,
        'user_id' => $javier->id,
        'transaction_id' => $groceries->id,
        'type' => AccountMemberLedgerEntryType::ExpensePaid,
        'amount' => 500.0,
        'description' => 'Groceries',
        'occurred_at' => $groceries->scheduled_at,
    ]);
    AccountMemberLedgerEntry::query()->create([
        'account_id' => $account->id,
        'user_id' => $divanny->id,
        'transaction_id' => $groceries->id,
        'related_user_id' => $javier->id,
        'type' => AccountMemberLedgerEntryType::ExpenseShare,
        'amount' => -250.0,
        'description' => 'Groceries',
        'occurred_at' => $groceries->scheduled_at,
    ]);
    AccountMemberLedgerEntry::query()->create([
        'account_id' => $account->id,
        'user_id' => $javier->id,
        'transaction_id' => $groceries->id,
        'related_user_id' => $javier->id,
        'type' => AccountMemberLedgerEntryType::ExpenseShare,
        'amount' => -250.0,
        'description' => 'Groceries',
        'occurred_at' => $groceries->scheduled_at,
    ]);

    $summary = app(BuildAccountMemberSummary::class)->execute($account);

    expect($summary['settlements_by_user'])->toMatchArray([
        ['user_id' => $javier->id, 'user_name' => 'Javier', 'amount' => 150.0],
        ['user_id' => $divanny->id, 'user_name' => 'Divanny', 'amount' => -150.0],
    ])
        ->and($summary['pending_reimbursements'])->toHaveCount(2)
        ->and($summary['pending_reimbursements'])->toContain([
            'from_user_id' => $javier->id,
            'from_user_name' => 'Javier',
            'to_user_id' => $divanny->id,
            'to_user_name' => 'Divanny',
            'amount' => 100.0,
            'action_type' => 'user_to_user',
            'items' => [
                [
                    'transaction_id' => (string) $filos->id,
                    'concept' => 'Filos',
                    'amount' => 100.0,
                    'occurred_at' => '2026-07-31',
                ],
            ],
        ])
        ->and($summary['pending_reimbursements'])->toContain([
            'from_user_id' => $divanny->id,
            'from_user_name' => 'Divanny',
            'to_user_id' => $javier->id,
            'to_user_name' => 'Javier',
            'amount' => 250.0,
            'action_type' => 'user_to_user',
            'items' => [
                [
                    'transaction_id' => (string) $groceries->id,
                    'concept' => 'Groceries',
                    'amount' => 250.0,
                    'occurred_at' => '2026-08-01',
                ],
            ],
        ]);
});

it('settles reimbursements without changing member custody', function () {
    $owner = User::factory()->create(['name' => 'Owner']);
    $partner = User::factory()->create(['name' => 'Partner']);
    $account = Account::factory()->create(['user_id' => $owner->id, 'balance' => 0.0]);
    $account->users()->sync([
        $owner->id => ['percentage' => 50],
        $partner->id => ['percentage' => 50],
    ]);
    $this->actingAs($partner);

    $transaction = app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'type' => TransactionType::Outcome,
        'status' => TransactionStatus::Completed,
        'concept' => 'Travel dinner',
        'amount' => 1000.0,
        'account_id' => $account->id,
        'payment_source' => TransactionPaymentSource::MemberOutOfPocket,
        'paid_by_user_id' => $partner->id,
        'split_between_users' => true,
        'user_payments' => [
            ['user_id' => $owner->id, 'percentage' => 50],
            ['user_id' => $partner->id, 'percentage' => 50],
        ],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]));

    app(RegisterAccountMemberTransfer::class)->execute($account, [
        'from_user_id' => $owner->id,
        'to_user_id' => $partner->id,
        'amount' => 500.0,
        'description' => 'Dinner reimbursement',
        'occurred_at' => now(),
    ]);

    $summary = app(BuildAccountMemberSummary::class)->execute($account);
    $settlementTransactionEntries = AccountMemberLedgerEntry::query()
        ->where('transaction_id', $transaction->id)
        ->where('type', AccountMemberLedgerEntryType::SettlementTransfer)
        ->orderBy('user_id')
        ->get();
    $recoveryTransaction = Transaction::query()
        ->where('account_id', $account->id)
        ->where('type', TransactionType::Income)
        ->where('concept', 'Dinner reimbursement')
        ->first();

    app(HydrateCurrentUserPendingReimbursements::class)->execute([$transaction], $owner->id);
    $ownerPendingAmount = $transaction->getAttribute('current_user_pending_reimbursement_amount');
    $ownerReceivableAmount = $transaction->getAttribute('current_user_receivable_reimbursement_amount');

    app(HydrateCurrentUserPendingReimbursements::class)->execute([$transaction], $partner->id);
    $partnerPendingAmount = $transaction->getAttribute('current_user_pending_reimbursement_amount');
    $partnerReceivableAmount = $transaction->getAttribute('current_user_receivable_reimbursement_amount');

    expect($summary['settlements_by_user'])->toMatchArray([
        ['user_id' => $owner->id, 'user_name' => 'Owner', 'amount' => 0.0],
        ['user_id' => $partner->id, 'user_name' => 'Partner', 'amount' => 0.0],
    ])
        ->and($summary['custody_by_user'])->toMatchArray([
            ['user_id' => $owner->id, 'user_name' => 'Owner', 'amount' => 0.0],
            ['user_id' => $partner->id, 'user_name' => 'Partner', 'amount' => 0.0],
        ])
        ->and($settlementTransactionEntries)->toHaveCount(2)
        ->and($settlementTransactionEntries->pluck('amount')->all())->toBe([500.0, -500.0])
        ->and($ownerPendingAmount)->toBe(0.0)
        ->and($ownerReceivableAmount)->toBe(0.0)
        ->and($partnerPendingAmount)->toBe(0.0)
        ->and($partnerReceivableAmount)->toBe(0.0)
        ->and($recoveryTransaction)->toBeNull()
        ->and((float) $account->fresh()->balance)->toBe(-1000.0)
        ->and($summary['pending_reimbursements'])->toBe([]);
});

it('closes transaction reimbursement badges when the account total has a rounding residual', function () {
    $javier = User::factory()->create(['name' => 'Javier']);
    $divanny = User::factory()->create(['name' => 'Divanny']);
    $account = Account::factory()->create(['user_id' => $javier->id]);
    $account->users()->sync([
        $javier->id => ['percentage' => 50],
        $divanny->id => ['percentage' => 50],
    ]);

    $fruteria = Transaction::factory()
        ->outcome()
        ->completed()
        ->create([
            'account_id' => $account->id,
            'user_id' => $javier->id,
            'concept' => 'Fruteria',
            'amount' => 433.0,
            'scheduled_at' => CarbonImmutable::parse('2026-07-05'),
        ]);
    $tombola = Transaction::factory()
        ->outcome()
        ->completed()
        ->create([
            'account_id' => $account->id,
            'user_id' => $javier->id,
            'concept' => 'La tombola',
            'amount' => 870.0,
            'scheduled_at' => CarbonImmutable::parse('2026-07-05'),
        ]);

    foreach ([[$fruteria, 216.5], [$tombola, 435.0]] as [$transaction, $amount]) {
        AccountMemberLedgerEntry::query()->create([
            'account_id' => $account->id,
            'user_id' => $javier->id,
            'transaction_id' => $transaction->id,
            'type' => AccountMemberLedgerEntryType::ExpensePaid,
            'amount' => $amount * 2,
            'description' => $transaction->concept,
            'occurred_at' => $transaction->scheduled_at,
        ]);
        AccountMemberLedgerEntry::query()->create([
            'account_id' => $account->id,
            'user_id' => $divanny->id,
            'transaction_id' => $transaction->id,
            'related_user_id' => $javier->id,
            'type' => AccountMemberLedgerEntryType::ExpenseShare,
            'amount' => $amount * -1,
            'description' => $transaction->concept,
            'occurred_at' => $transaction->scheduled_at,
        ]);
    }

    AccountMemberLedgerEntry::query()->create([
        'account_id' => $account->id,
        'user_id' => $javier->id,
        'related_user_id' => $divanny->id,
        'type' => AccountMemberLedgerEntryType::LegacySettlement,
        'amount' => -0.04,
        'description' => 'Legacy rounding',
        'occurred_at' => CarbonImmutable::parse('2026-07-06'),
    ]);
    AccountMemberLedgerEntry::query()->create([
        'account_id' => $account->id,
        'user_id' => $divanny->id,
        'related_user_id' => $javier->id,
        'type' => AccountMemberLedgerEntryType::LegacySettlement,
        'amount' => 0.04,
        'description' => 'Legacy rounding',
        'occurred_at' => CarbonImmutable::parse('2026-07-06'),
    ]);

    $pendingReimbursement = app(BuildAccountMemberSummary::class)->execute($account)['pending_reimbursements'][0];

    app(RegisterAccountMemberTransfer::class)->execute($account, [
        'from_user_id' => $divanny->id,
        'to_user_id' => $javier->id,
        'amount' => $pendingReimbursement['amount'],
        'description' => 'Reembolso de Divanny a Javier',
        'occurred_at' => CarbonImmutable::parse('2026-07-06'),
    ]);

    app(HydrateCurrentUserPendingReimbursements::class)->execute([$fruteria, $tombola], $divanny->id);
    $summary = app(BuildAccountMemberSummary::class)->execute($account);
    $roundingAdjustment = AccountMemberLedgerEntry::query()
        ->where('account_id', $account->id)
        ->where('user_id', $divanny->id)
        ->whereNull('transaction_id')
        ->where('type', AccountMemberLedgerEntryType::SettlementTransfer)
        ->latest('id')
        ->first();

    expect($pendingReimbursement['amount'])->toBe(651.46)
        ->and($fruteria->getAttribute('current_user_pending_reimbursement_amount'))->toBe(0.0)
        ->and($tombola->getAttribute('current_user_pending_reimbursement_amount'))->toBe(0.0)
        ->and($summary['pending_reimbursements'])->toBe([])
        ->and((float) $roundingAdjustment->amount)->toBe(-0.04);
});

it('uses debtor open transaction balances for reimbursement detail items', function () {
    $javier = User::factory()->create(['name' => 'Javier']);
    $divanny = User::factory()->create(['name' => 'Divanny']);
    $account = Account::factory()->create(['user_id' => $javier->id]);
    $account->users()->sync([
        $javier->id => ['percentage' => 50],
        $divanny->id => ['percentage' => 50],
    ]);

    $legacyTransaction = Transaction::factory()
        ->outcome()
        ->completed()
        ->create([
            'account_id' => $account->id,
            'user_id' => $javier->id,
            'concept' => 'Legacy medicine',
            'amount' => 777.0,
            'scheduled_at' => CarbonImmutable::parse('2026-05-27'),
        ]);
    $juneDoctor = Transaction::factory()
        ->outcome()
        ->completed()
        ->create([
            'account_id' => $account->id,
            'user_id' => $divanny->id,
            'concept' => 'Dr. Abraham 2-junio',
            'amount' => 1100.0,
            'scheduled_at' => CarbonImmutable::parse('2026-07-15'),
        ]);
    $julyDoctor = Transaction::factory()
        ->outcome()
        ->completed()
        ->create([
            'account_id' => $account->id,
            'user_id' => $divanny->id,
            'concept' => 'Dr. Abraham 2-jul',
            'amount' => 1500.0,
            'scheduled_at' => CarbonImmutable::parse('2026-07-15'),
        ]);

    AccountMemberLedgerEntry::query()->create([
        'account_id' => $account->id,
        'user_id' => $javier->id,
        'transaction_id' => $legacyTransaction->id,
        'related_user_id' => $divanny->id,
        'type' => AccountMemberLedgerEntryType::SettlementTransfer,
        'amount' => -777.0,
        'description' => 'Legacy medicine',
        'occurred_at' => $legacyTransaction->scheduled_at,
    ]);
    AccountMemberLedgerEntry::query()->create([
        'account_id' => $account->id,
        'user_id' => $divanny->id,
        'transaction_id' => $legacyTransaction->id,
        'related_user_id' => $javier->id,
        'type' => AccountMemberLedgerEntryType::SettlementTransfer,
        'amount' => 777.0,
        'description' => 'Legacy medicine',
        'occurred_at' => $legacyTransaction->scheduled_at,
    ]);

    foreach ([[$juneDoctor, 1100.0], [$julyDoctor, 1500.0]] as [$transaction, $amount]) {
        AccountMemberLedgerEntry::query()->create([
            'account_id' => $account->id,
            'user_id' => $javier->id,
            'transaction_id' => $transaction->id,
            'related_user_id' => $divanny->id,
            'type' => AccountMemberLedgerEntryType::ExpenseShare,
            'amount' => $amount * -1,
            'description' => $transaction->concept,
            'occurred_at' => $transaction->scheduled_at,
        ]);
        AccountMemberLedgerEntry::query()->create([
            'account_id' => $account->id,
            'user_id' => $divanny->id,
            'transaction_id' => $transaction->id,
            'type' => AccountMemberLedgerEntryType::ExpensePaid,
            'amount' => $amount,
            'description' => $transaction->concept,
            'occurred_at' => $transaction->scheduled_at,
        ]);
    }

    AccountMemberLedgerEntry::query()->create([
        'account_id' => $account->id,
        'user_id' => $javier->id,
        'type' => AccountMemberLedgerEntryType::LegacySettlement,
        'amount' => 777.0,
        'description' => 'Legacy offset',
        'occurred_at' => CarbonImmutable::parse('2026-07-16'),
    ]);
    AccountMemberLedgerEntry::query()->create([
        'account_id' => $account->id,
        'user_id' => $divanny->id,
        'type' => AccountMemberLedgerEntryType::LegacySettlement,
        'amount' => -777.0,
        'description' => 'Legacy offset',
        'occurred_at' => CarbonImmutable::parse('2026-07-16'),
    ]);

    $summary = app(BuildAccountMemberSummary::class)->execute($account);

    expect($summary['pending_reimbursements'])->toHaveCount(1)
        ->and($summary['pending_reimbursements'][0]['amount'])->toBe(2600.0)
        ->and($summary['pending_reimbursements'][0]['items'])->toBe([
            [
                'transaction_id' => (string) $julyDoctor->id,
                'concept' => 'Dr. Abraham 2-jul',
                'amount' => 1500.0,
                'occurred_at' => '2026-07-15',
            ],
            [
                'transaction_id' => (string) $juneDoctor->id,
                'concept' => 'Dr. Abraham 2-junio',
                'amount' => 1100.0,
                'occurred_at' => '2026-07-15',
            ],
        ]);
});
