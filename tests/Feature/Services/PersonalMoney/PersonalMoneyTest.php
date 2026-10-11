<?php

declare(strict_types=1);

use App\Dto\PersonalMoneySettingsDto;
use App\Dto\TransactionFormDto;
use App\Enums\TransactionPaymentSource;
use App\Models\Account;
use App\Models\AccountMemberLedgerEntry;
use App\Models\PersonalMoneyLink;
use App\Models\PersonalMoneyReimbursement;
use App\Models\PersonalMoneySetting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounts\RegisterAccountMemberTransfer;
use App\Services\PersonalMoney\BuildPersonalMoneySources;
use App\Services\PersonalMoney\BuildPersonalMoneySummary;
use App\Services\PersonalMoney\EligiblePersonalMoneyAccounts;
use App\Services\PersonalMoney\ResolvePersonalMoneySource;
use App\Services\PersonalMoney\UpdatePersonalMoneySettings;
use App\Services\Transaction\TransactionCreator;
use App\Services\Transaction\TransactionRemover;
use App\Services\Transaction\TransactionUpdater;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 10, 11)->setTime(12, 0));
    $this->user = User::factory()->create();
    $this->partner = User::factory()->create();
    $this->personal = Account::factory()->create([
        'name' => 'My spending', 'user_id' => $this->user->id, 'virtual' => false, 'credit_card' => false, 'balance' => 0,
    ]);
    $this->personal->users()->attach($this->user->id, ['percentage' => 100]);
    $this->shared = Account::factory()->create([
        'name' => 'Mini US', 'user_id' => $this->user->id, 'virtual' => false, 'credit_card' => false, 'balance' => 0,
    ]);
    $this->shared->users()->sync([
        $this->user->id => ['percentage' => 50], $this->partner->id => ['percentage' => 50],
    ]);
    $this->actingAs($this->user);
    $this->setting = app(UpdatePersonalMoneySettings::class)->execute($this->user->id,
        new PersonalMoneySettingsDto($this->personal->id, '2026-10-01', 1000, true));
});

function personalMoneyTransaction(Account $account, User $creator, string $type, float $amount, array $attributes = []): Transaction
{
    return Transaction::factory()->create(array_merge([
        'account_id'                => $account->id, 'user_id' => $creator->id, 'type' => $type,
        'amount'                    => $amount, 'scheduled_at' => '2026-10-05 12:00:00',
        'uses_shared_account_rules' => $account->users()->count() > 1,
    ], $attributes));
}

function personalMoneyResolve(User $user, Transaction $transaction, string $resolution = 'personal'): void
{
    app(ResolvePersonalMoneySource::class)->execute($user->id, 'transaction', $transaction->id, $resolution);
}

function personalMoneyAvailable(User $user): ?float
{
    return app(BuildPersonalMoneySummary::class)->execute($user->id)['available_balance'];
}

it('only offers exclusively owned individual non investment accounts', function (): void {
    expect(app(EligiblePersonalMoneyAccounts::class)->execute($this->user->id)->pluck('id')->all())->toBe([$this->personal->id]);
    $this->personal->update(['virtual' => true]);
    expect(app(EligiblePersonalMoneyAccounts::class)->execute($this->user->id)->count())->toBe(0);
    expect(fn () => app(UpdatePersonalMoneySettings::class)->execute($this->user->id,
        new PersonalMoneySettingsDto($this->shared->id, '2026-10-01', 0, true)))->toThrow(ValidationException::class);
});

it('counts personal salary and expenses once while excluding history future and technical incomes', function (): void {
    personalMoneyTransaction($this->personal, $this->user, 'income', 20000);
    personalMoneyTransaction($this->personal, $this->user, 'outcome', 500);
    personalMoneyTransaction($this->personal, $this->user, 'income', 10000, ['scheduled_at' => '2026-09-30']);
    personalMoneyTransaction($this->personal, $this->user, 'income', 10000, ['scheduled_at' => '2026-10-12']);
    personalMoneyTransaction($this->personal, $this->user, 'income', 10000, ['legacy_migrated_at' => now()]);
    $parent = personalMoneyTransaction($this->personal, $this->user, 'outcome', 10);
    personalMoneyTransaction($this->personal, $this->user, 'income', 10, ['parent_transaction_id' => $parent->id]);
    expect(personalMoneyAvailable($this->user))->toBe(20490.0);
    expect($this->personal->fresh()->balance)->toBe(0.0);
});

it('subtracts contributions without subtracting the later account fund purchase again', function (): void {
    $contribution = personalMoneyTransaction($this->shared, $this->user, 'income', 200);
    personalMoneyTransaction($this->shared, $this->partner, 'outcome', 30, [
        'payment_source' => TransactionPaymentSource::AccountFund, 'paid_by_user_id' => $this->partner->id,
    ]);
    $summary = app(BuildPersonalMoneySummary::class)->execute($this->user->id);
    expect($summary['pending_count'])->toBe(1)->and($summary['available_balance'])->toBe(1000.0);
    personalMoneyResolve($this->user, $contribution);
    personalMoneyResolve($this->user, $contribution);
    expect(personalMoneyAvailable($this->user))->toBe(800.0)->and(PersonalMoneyLink::query()->count())->toBe(1);
    personalMoneyResolve($this->user, $contribution, 'external');
    expect(personalMoneyAvailable($this->user))->toBe(1000.0);
    personalMoneyResolve($this->user, $contribution, 'pending');
    expect(PersonalMoneyLink::query()->sole()->resolution)->toBe('pending');
});

it('requires the actual payer to resolve an out of pocket payment recorded by another member', function (): void {
    $expense = personalMoneyTransaction($this->shared, $this->partner, 'outcome', 400, [
        'paid_by_user_id' => $this->user->id, 'payment_source' => TransactionPaymentSource::MemberOutOfPocket,
    ]);
    personalMoneyResolve($this->user, $expense);
    expect(personalMoneyAvailable($this->user))->toBe(600.0);
    $partnerPersonal = Account::factory()->create(['user_id' => $this->partner->id, 'virtual' => false, 'credit_card' => false]);
    $partnerPersonal->users()->attach($this->partner->id);
    app(UpdatePersonalMoneySettings::class)->execute($this->partner->id,
        new PersonalMoneySettingsDto($partnerPersonal->id, '2026-10-01', 500, true));
    expect(app(BuildPersonalMoneySources::class)->execute($this->partner->id,
        PersonalMoneySetting::query()->where('user_id', $this->partner->id)->first())->count())->toBe(0);
    expect(fn () => personalMoneyResolve($this->partner, $expense))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('includes partial outgoing and incoming reimbursements without counting self custody or corrections', function (): void {
    $entries = app(RegisterAccountMemberTransfer::class)->execute($this->shared, [
        'from_user_id' => $this->partner->id, 'to_user_id' => $this->user->id,
        'amount'       => 125, 'description' => 'Partial reimbursement', 'occurred_at' => '2026-10-06',
    ]);
    $incoming = collect($entries)->firstWhere('user_id', $this->user->id);
    app(ResolvePersonalMoneySource::class)->execute($this->user->id, 'reimbursement', PersonalMoneyReimbursement::query()->where('ledger_entry_key', $incoming->id)->value('id'), 'personal');
    $outgoingEntries = app(RegisterAccountMemberTransfer::class)->execute($this->shared, [
        'from_user_id' => $this->user->id, 'to_user_id' => $this->partner->id,
        'amount'       => 25, 'occurred_at' => '2026-10-07',
    ]);
    $outgoing = collect($outgoingEntries)->firstWhere('user_id', $this->user->id);
    app(ResolvePersonalMoneySource::class)->execute($this->user->id, 'reimbursement', PersonalMoneyReimbursement::query()->where('ledger_entry_key', $outgoing->id)->value('id'), 'personal');
    foreach (['settlement_correction', 'custody_reimbursement_payment', 'settlement_transfer'] as $type) {
        AccountMemberLedgerEntry::query()->create([
            'account_id' => $this->shared->id, 'user_id' => $this->user->id, 'related_user_id' => $this->user->id,
            'type'       => $type, 'amount' => 999, 'occurred_at' => '2026-10-07',
        ]);
    }
    expect(personalMoneyAvailable($this->user))->toBe(1100.0);
    expect(app(BuildPersonalMoneySummary::class)->execute($this->user->id)['history'])->toHaveCount(2);
});

it('recalculates edited amounts and removes effects when the transaction is deleted', function (): void {
    $transaction = app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'account_id'        => $this->shared->id, 'type' => 'income', 'status' => 'completed',
        'concept'           => 'Contribution', 'amount' => 200, 'scheduled_at' => '2026-10-05',
        'custodian_user_id' => $this->user->id, 'cash_source' => 'personal',
    ]));
    expect(personalMoneyAvailable($this->user))->toBe(800.0);
    app(TransactionUpdater::class)->execute($transaction, TransactionFormDto::fromFormArray([
        'account_id'        => $this->shared->id, 'type' => 'income', 'status' => 'completed',
        'concept'           => 'Corrected contribution', 'amount' => 300, 'scheduled_at' => '2026-10-05',
        'custodian_user_id' => $this->user->id,
    ]));
    expect(personalMoneyAvailable($this->user))->toBe(700.0);
    app(TransactionRemover::class)->execute($transaction->fresh());
    expect(personalMoneyAvailable($this->user))->toBe(1000.0)->and(PersonalMoneyLink::query()->count())->toBe(0);
});

it('retires incompatible links when a payment changes payer even if it later changes back', function (): void {
    $transaction = app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'account_id'      => $this->shared->id, 'type' => 'outcome', 'status' => 'completed',
        'concept'         => 'Purchase', 'amount' => 200, 'scheduled_at' => '2026-10-05',
        'paid_by_user_id' => $this->user->id, 'payment_source' => 'member_out_of_pocket', 'cash_source' => 'personal',
    ]));
    expect(personalMoneyAvailable($this->user))->toBe(800.0);
    foreach ([$this->partner->id, $this->user->id] as $payerId) {
        $transaction = app(TransactionUpdater::class)->execute($transaction, TransactionFormDto::fromFormArray([
            'account_id'      => $this->shared->id, 'type' => 'outcome', 'status' => 'completed',
            'concept'         => 'Purchase', 'amount' => 200, 'scheduled_at' => '2026-10-05',
            'paid_by_user_id' => $payerId, 'payment_source' => 'member_out_of_pocket',
        ]));
    }
    expect(personalMoneyAvailable($this->user))->toBe(1000.0)
        ->and(app(BuildPersonalMoneySummary::class)->execute($this->user->id)['pending_count'])->toBe(1)
        ->and(PersonalMoneyLink::query()->count())->toBe(0);
});

it('suspends calculation when the selected personal account becomes shared and permits disabling', function (): void {
    $this->personal->users()->attach($this->partner->id);
    expect(personalMoneyAvailable($this->user))->toBeNull();
    app(UpdatePersonalMoneySettings::class)->execute($this->user->id,
        new PersonalMoneySettingsDto($this->personal->id, '2026-10-01', 1000, false));
    expect(app(BuildPersonalMoneySummary::class)->execute($this->user->id)['settings']['enabled'])->toBeFalse();
});

it('paginates history while calculating totals and pending counts over every source', function (): void {
    for ($i = 0; $i < 5; ++$i) {
        personalMoneyTransaction($this->personal, $this->user, 'income', 10);
        personalMoneyTransaction($this->shared, $this->partner, 'income', 20);
    }
    $summary = app(BuildPersonalMoneySummary::class)->execute($this->user->id, 2, 3);
    expect($summary['available_balance'])->toBe(1050.0)->and($summary['pending_count'])->toBe(5)
        ->and($summary['history'])->toHaveCount(3)->and($summary['meta']['total'])->toBe(10);
    $pending = app(BuildPersonalMoneySummary::class)->execute($this->user->id, 1, 3, 'pending');
    expect($pending['meta']['total'])->toBe(5)->and($pending['available_balance'])->toBe(1050.0);
});

it('preserves data when disabled without applying private effects', function (): void {
    $transaction = personalMoneyTransaction($this->shared, $this->user, 'income', 200);
    personalMoneyResolve($this->user, $transaction);
    app(UpdatePersonalMoneySettings::class)->execute($this->user->id,
        new PersonalMoneySettingsDto($this->personal->id, '2026-10-01', 1000, false));
    $summary = app(BuildPersonalMoneySummary::class)->execute($this->user->id);
    expect($summary['available_balance'])->toBeNull()->and($summary['history'])->toBe([])
        ->and(PersonalMoneyLink::query()->count())->toBe(1);
});

it('keeps actual reimbursement cash after the original shared expense is edited or deleted', function (): void {
    $transaction = app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'account_id'          => $this->shared->id, 'type' => 'outcome', 'status' => 'completed',
        'concept'             => 'Shared purchase', 'amount' => 200, 'scheduled_at' => '2026-10-05',
        'paid_by_user_id'     => $this->user->id, 'payment_source' => 'member_out_of_pocket',
        'split_between_users' => true, 'user_payments' => [
            ['user_id' => $this->user->id, 'percentage' => 50], ['user_id' => $this->partner->id, 'percentage' => 50],
        ], 'cash_source' => 'personal',
    ]));
    app(RegisterAccountMemberTransfer::class)->execute($this->shared, [
        'from_user_id' => $this->partner->id, 'to_user_id' => $this->user->id,
        'amount'       => 100, 'occurred_at' => '2026-10-06',
    ]);
    $cash = PersonalMoneyReimbursement::query()->where('user_id', $this->user->id)->where('cash_effect', 100)->firstOrFail();
    app(ResolvePersonalMoneySource::class)->execute($this->user->id, 'reimbursement', $cash->id, 'personal');
    expect(personalMoneyAvailable($this->user))->toBe(900.0);
    $transaction = app(TransactionUpdater::class)->execute($transaction, TransactionFormDto::fromFormArray([
        'account_id'      => $this->shared->id, 'type' => 'outcome', 'status' => 'completed',
        'concept'         => 'Corrected shared purchase', 'amount' => 150, 'scheduled_at' => '2026-10-05',
        'paid_by_user_id' => $this->user->id, 'payment_source' => 'member_out_of_pocket',
    ]));
    expect(AccountMemberLedgerEntry::query()->whereKey($cash->ledger_entry_key)->exists())->toBeFalse()
        ->and(personalMoneyAvailable($this->user))->toBe(950.0);
    app(TransactionRemover::class)->execute($transaction);
    expect(personalMoneyAvailable($this->user))->toBe(1100.0);
});

it('returns reserved money to available cash on a self custody reimbursement', function (): void {
    app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'account_id'        => $this->shared->id, 'type' => 'income', 'status' => 'completed',
        'concept'           => 'Reserved fund', 'amount' => 200, 'scheduled_at' => '2026-10-04',
        'custodian_user_id' => $this->user->id, 'cash_source' => 'personal',
    ]));
    app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'account_id'      => $this->shared->id, 'type' => 'outcome', 'status' => 'completed',
        'concept'         => 'Out of pocket', 'amount' => 50, 'scheduled_at' => '2026-10-05',
        'paid_by_user_id' => $this->user->id, 'payment_source' => 'member_out_of_pocket',
        'cash_source'     => 'personal',
    ]));
    expect(personalMoneyAvailable($this->user))->toBe(750.0);
    app(RegisterAccountMemberTransfer::class)->execute($this->shared, [
        'from_user_id' => $this->user->id, 'to_user_id' => $this->user->id,
        'action_type'  => 'custody_to_user', 'amount' => 50, 'occurred_at' => '2026-10-06',
    ]);
    $cash = PersonalMoneyReimbursement::query()->where('user_id', $this->user->id)->where('cash_effect', 50)->firstOrFail();
    app(ResolvePersonalMoneySource::class)->execute($this->user->id, 'reimbursement', $cash->id, 'personal');
    expect(personalMoneyAvailable($this->user))->toBe(800.0);
});

it('does not deduct reserved custody payments from the custodians free money twice', function (): void {
    app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'account_id'        => $this->shared->id, 'type' => 'income', 'status' => 'completed',
        'concept'           => 'Reserved fund', 'amount' => 200, 'scheduled_at' => '2026-10-04',
        'custodian_user_id' => $this->user->id, 'cash_source' => 'personal',
    ]));
    app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'account_id'      => $this->shared->id, 'type' => 'outcome', 'status' => 'completed',
        'concept'         => 'Partner paid', 'amount' => 50, 'scheduled_at' => '2026-10-05',
        'paid_by_user_id' => $this->partner->id, 'payment_source' => 'member_out_of_pocket',
    ]));
    app(RegisterAccountMemberTransfer::class)->execute($this->shared, [
        'from_user_id' => $this->user->id, 'to_user_id' => $this->partner->id,
        'action_type'  => 'custody_to_user', 'amount' => 50, 'occurred_at' => '2026-10-06',
    ]);
    expect(personalMoneyAvailable($this->user))->toBe(800.0)
        ->and(app(BuildPersonalMoneySummary::class)->execute($this->user->id)['pending_count'])->toBe(0)
        ->and(PersonalMoneyReimbursement::query()->where('user_id', $this->partner->id)->sum('cash_effect'))->toBe(50);
});

it('preserves and captures existing reimbursement entries on activation without inferring a personal source', function (): void {
    $this->setting->delete();
    $sent = AccountMemberLedgerEntry::query()->create([
        'account_id' => $this->shared->id, 'user_id' => $this->user->id, 'related_user_id' => $this->partner->id,
        'type'       => 'settlement_transfer', 'amount' => 60, 'occurred_at' => '2026-10-06', 'description' => 'Existing payment',
    ]);
    $received = AccountMemberLedgerEntry::query()->create([
        'account_id' => $this->shared->id, 'user_id' => $this->user->id, 'related_user_id' => $this->partner->id,
        'type'       => 'settlement_transfer', 'amount' => -20, 'occurred_at' => '2026-10-06', 'description' => 'Existing receipt',
    ]);
    app(UpdatePersonalMoneySettings::class)->execute($this->user->id,
        new PersonalMoneySettingsDto($this->personal->id, '2026-10-01', 1000, true));
    expect(app(BuildPersonalMoneySummary::class)->execute($this->user->id)['pending_count'])->toBe(2)
        ->and(personalMoneyAvailable($this->user))->toBe(1000.0);
    $cash = PersonalMoneyReimbursement::query()->where('ledger_entry_key', $sent->id)->firstOrFail();
    app(ResolvePersonalMoneySource::class)->execute($this->user->id, 'reimbursement', $cash->id, 'personal');
    $sent->delete();
    expect(personalMoneyAvailable($this->user))->toBe(940.0);
    app(UpdatePersonalMoneySettings::class)->execute($this->user->id,
        new PersonalMoneySettingsDto($this->personal->id, '2026-10-01', 1000, true));
    expect(PersonalMoneyReimbursement::query()->count())->toBe(2);
});

it('never turns historical shared contributions into salary when selecting a formerly shared account', function (): void {
    $income = personalMoneyTransaction($this->shared, $this->user, 'income', 300);
    $this->shared->users()->detach($this->partner->id);
    app(UpdatePersonalMoneySettings::class)->execute($this->user->id,
        new PersonalMoneySettingsDto($this->shared->id, '2026-10-01', 1000, true));
    $summary = app(BuildPersonalMoneySummary::class)->execute($this->user->id);
    expect($summary['available_balance'])->toBe(1000.0)
        ->and($summary['history'][0]['kind'])->toBe('contribution')
        ->and($summary['history'][0]['resolution'])->toBe('pending');
});

it('rejects assigning a full contribution to two personal funds', function (): void {
    $income = personalMoneyTransaction($this->shared, $this->user, 'income', 200);
    personalMoneyResolve($this->user, $income);
    $otherFund = Account::factory()->create(['user_id' => $this->partner->id, 'virtual' => false, 'credit_card' => false]);
    $otherFund->users()->attach($this->partner->id);
    app(UpdatePersonalMoneySettings::class)->execute($this->partner->id,
        new PersonalMoneySettingsDto($otherFund->id, '2026-10-01', 1000, true));
    expect(fn () => personalMoneyResolve($this->partner, $income))->toThrow(ValidationException::class);
    expect(personalMoneyAvailable($this->partner))->toBe(1000.0);
    personalMoneyResolve($this->partner, $income, 'external');
    expect(personalMoneyAvailable($this->user))->toBe(800.0);
});

it('retains only the private historical snapshot after leaving a shared account', function (): void {
    $transaction = personalMoneyTransaction($this->shared, $this->partner, 'outcome', 200, [
        'paid_by_user_id' => $this->user->id, 'payment_source' => TransactionPaymentSource::MemberOutOfPocket,
        'concept'         => 'Previously paid purchase',
    ]);
    personalMoneyResolve($this->user, $transaction);
    app(RegisterAccountMemberTransfer::class)->execute($this->shared, [
        'from_user_id' => $this->partner->id, 'to_user_id' => $this->user->id,
        'amount'       => 50, 'occurred_at' => '2026-10-06',
    ]);
    $cash = PersonalMoneyReimbursement::query()->where('user_id', $this->user->id)->where('cash_effect', 50)->firstOrFail();
    app(ResolvePersonalMoneySource::class)->execute($this->user->id, 'reimbursement', $cash->id, 'personal');
    expect(personalMoneyAvailable($this->user))->toBe(850.0);
    $this->shared->users()->detach($this->user->id);
    $this->shared->update(['name' => 'New private name']);
    $this->actingAs($this->partner);
    app(TransactionUpdater::class)->execute($transaction, TransactionFormDto::fromFormArray([
        'account_id'      => $this->shared->id, 'type' => 'outcome', 'status' => 'completed',
        'concept'         => 'New private description', 'amount' => 300, 'scheduled_at' => '2026-10-05',
        'paid_by_user_id' => $this->user->id, 'payment_source' => 'member_out_of_pocket',
    ]));
    $summary = app(BuildPersonalMoneySummary::class)->execute($this->user->id);
    expect($summary['available_balance'])->toBe(850.0)
        ->and(collect($summary['history'])->where('source_type', 'transaction')->first()['concept'])->toBe('Previously paid purchase')
        ->and(collect($summary['history'])->pluck('account_name')->unique()->all())->toBe(['Mini US']);
    app(TransactionUpdater::class)->execute($transaction->fresh(), TransactionFormDto::fromFormArray([
        'account_id'      => $this->shared->id, 'type' => 'outcome', 'status' => 'completed',
        'concept'         => 'Different payer after removal', 'amount' => 400, 'scheduled_at' => '2026-10-05',
        'paid_by_user_id' => $this->partner->id, 'payment_source' => 'member_out_of_pocket',
    ]));
    expect(personalMoneyAvailable($this->user))->toBe(850.0);
    personalMoneyResolve($this->user, $transaction, 'pending');
    $pending = app(BuildPersonalMoneySummary::class)->execute($this->user->id);
    expect($pending['pending_count'])->toBe(1)->and($pending['available_balance'])->toBe(1050.0);
});

it('preserves previously linked cash when a shared account is archived', function (): void {
    $income = personalMoneyTransaction($this->shared, $this->user, 'income', 200);
    personalMoneyResolve($this->user, $income);
    $this->shared->delete();
    expect(personalMoneyAvailable($this->user))->toBe(800.0);
});
