<?php

declare(strict_types=1);

use App\Dto\PersonalMoneySettingsDto;
use App\Dto\TransactionFormDto;
use App\Models\Account;
use App\Models\AccountMemberLedgerEntry;
use App\Models\PersonalMoneyLink;
use App\Models\PersonalMoneySetting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PersonalMoney\BuildPersonalMoneySummary;
use App\Services\PersonalMoney\CaptureExistingPersonalMoneyReimbursements;
use App\Services\PersonalMoney\ResolvePersonalMoneySource;
use App\Services\PersonalMoney\UpdatePersonalMoneySettings;
use App\Services\Transaction\TransactionCreator;
use App\Services\Transaction\TransactionUpdater;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 10, 11)->setTime(15, 0));
    $this->user = User::factory()->create();
    $this->partner = User::factory()->create();
    $this->personal = Account::factory()->create(['user_id' => $this->user->id, 'virtual' => false, 'credit_card' => false, 'balance' => 0]);
    $this->personal->users()->attach($this->user->id);
    $this->shared = Account::factory()->create(['user_id' => $this->user->id, 'virtual' => false, 'credit_card' => false, 'balance' => 0]);
    $this->shared->users()->sync([$this->user->id => ['percentage' => 50], $this->partner->id => ['percentage' => 50]]);
    $this->actingAs($this->user);
});

function activatePersonalMoneyNow(Account $account, User $user, float $balance = 10000, ?string $key = null): PersonalMoneySetting
{
    return app(UpdatePersonalMoneySettings::class)->execute($user->id,
        new PersonalMoneySettingsDto($account->id, null, $balance, true, 'now', $key ?? (string) Str::uuid()));
}

function createActivationMovement(Account $account, User $payer, array $overrides = []): Transaction
{
    return app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray(array_replace([
        'account_id'      => $account->id, 'type' => 'outcome', 'status' => 'completed',
        'concept'         => 'New payment', 'amount' => 100, 'scheduled_at' => '2026-10-11',
        'paid_by_user_id' => $payer->id, 'payment_source' => 'member_out_of_pocket',
    ], $overrides)));
}

it('starts with the exact opening amount and separates movements created in the same second', function (): void {
    createActivationMovement($this->personal, $this->user);
    createActivationMovement($this->shared, $this->user);
    $setting = activatePersonalMoneyNow($this->personal, $this->user);
    $summary = app(BuildPersonalMoneySummary::class)->execute($this->user->id);
    expect($summary['available_balance'])->toBe(10000.0)->and($summary['history'])->toBe([])->and($summary['pending_count'])->toBe(0);
    createActivationMovement($this->personal, $this->user);
    createActivationMovement($this->shared, $this->user);
    expect(app(BuildPersonalMoneySummary::class)->execute($this->user->id)['available_balance'])->toBe(9800.0);
    expect($setting->activated_at->toDateTimeString())->toBe('2026-10-11 15:00:00');
});

it('excludes late snapshots of preactivation reimbursements', function (): void {
    AccountMemberLedgerEntry::query()->create([
        'account_id' => $this->shared->id, 'user_id' => $this->user->id, 'related_user_id' => $this->partner->id,
        'type'       => 'settlement_transfer', 'amount' => 125, 'occurred_at' => '2026-10-01',
    ]);
    activatePersonalMoneyNow($this->personal, $this->user);
    app(CaptureExistingPersonalMoneyReimbursements::class)->execute($this->user->id, '2026-10-01');
    expect(app(BuildPersonalMoneySummary::class)->execute($this->user->id)['history'])->toBe([]);
});

it('defaults own new shared payments to personal money while respecting explicit choices', function (): void {
    activatePersonalMoneyNow($this->personal, $this->user);
    $automatic = createActivationMovement($this->shared, $this->user);
    $external = createActivationMovement($this->shared, $this->user, ['cash_source' => 'external']);
    $pending = createActivationMovement($this->shared, $this->user, ['cash_source' => 'pending']);
    expect(PersonalMoneyLink::query()->where('transaction_id', $automatic->id)->sole()->resolution)->toBe('personal');
    expect(PersonalMoneyLink::query()->where('transaction_id', $external->id)->sole()->resolution)->toBe('external');
    expect(PersonalMoneyLink::query()->where('transaction_id', $pending->id)->sole()->resolution)->toBe('pending');
    expect(app(BuildPersonalMoneySummary::class)->execute($this->user->id)['available_balance'])->toBe(9900.0);
});

it('reviews payments recorded by others only for the actual payer', function (): void {
    activatePersonalMoneyNow($this->personal, $this->user);
    $this->actingAs($this->partner);
    createActivationMovement($this->shared, $this->user);
    createActivationMovement($this->shared, $this->partner);
    $summary = app(BuildPersonalMoneySummary::class)->execute($this->user->id);
    expect($summary['history'])->toHaveCount(1)->and($summary['pending_count'])->toBe(1)->and($summary['available_balance'])->toBe(10000.0);
});

it('requires an explicit historical effect and preserves the sign of edited personal income', function (): void {
    activatePersonalMoneyNow($this->personal, $this->user);
    $income = createActivationMovement($this->personal, $this->user, ['type' => 'income', 'scheduled_at' => '2026-10-01']);
    $expense = createActivationMovement($this->personal, $this->user, ['scheduled_at' => '2026-10-01', 'cash_source' => 'external']);
    $summary = app(BuildPersonalMoneySummary::class)->execute($this->user->id);
    expect($summary['available_balance'])->toBe(10000.0)->and($summary['pending_count'])->toBe(1);
    app(ResolvePersonalMoneySource::class)->execute($this->user->id, 'transaction', $income->id, 'personal');
    app(TransactionUpdater::class)->execute($income, TransactionFormDto::fromFormArray([
        'account_id' => $this->personal->id, 'type' => 'income', 'status' => 'completed',
        'concept'    => 'Corrected income', 'amount' => 250, 'scheduled_at' => '2026-10-01',
    ]));
    expect(app(BuildPersonalMoneySummary::class)->execute($this->user->id)['available_balance'])->toBe(10250.0);
    expect(PersonalMoneyLink::query()->where('transaction_id', $income->id)->sole()->cash_effect)->toBe(250.0);
    expect(PersonalMoneyLink::query()->where('transaction_id', $expense->id)->sole()->resolution)->toBe('external');
});

it('does not automatically link historical shared payments or rewrite a choice on edit', function (): void {
    activatePersonalMoneyNow($this->personal, $this->user);
    $transaction = createActivationMovement($this->shared, $this->user, ['scheduled_at' => '2026-10-01']);
    expect(app(BuildPersonalMoneySummary::class)->execute($this->user->id)['pending_count'])->toBe(1);
    app(ResolvePersonalMoneySource::class)->execute($this->user->id, 'transaction', $transaction->id, 'external');
    app(TransactionUpdater::class)->execute($transaction, TransactionFormDto::fromFormArray([
        'account_id'      => $this->shared->id, 'type' => 'outcome', 'status' => 'completed',
        'concept'         => 'Changed amount', 'amount' => 250, 'scheduled_at' => '2026-10-01',
        'paid_by_user_id' => $this->user->id, 'payment_source' => 'member_out_of_pocket',
    ]));
    expect(app(BuildPersonalMoneySummary::class)->execute($this->user->id)['available_balance'])->toBe(10000.0);
});

it('keeps activation retries idempotent including after another baseline operation', function (): void {
    $firstKey = (string) Str::uuid();
    $setting = activatePersonalMoneyNow($this->personal, $this->user, 10000, $firstKey);
    createActivationMovement($this->personal, $this->user);
    $this->travel(1)->hours();
    $retry = activatePersonalMoneyNow($this->personal, $this->user, 10000, $firstKey);
    expect($retry->activated_at->eq($setting->activated_at))->toBeTrue();
    expect(app(BuildPersonalMoneySummary::class)->execute($this->user->id)['available_balance'])->toBe(9900.0);
    activatePersonalMoneyNow($this->personal, $this->user, 9000);
    activatePersonalMoneyNow($this->personal, $this->user, 10000, $firstKey);
    expect(app(BuildPersonalMoneySummary::class)->execute($this->user->id)['available_balance'])->toBe(9000.0);
    expect(fn () => activatePersonalMoneyNow($this->personal, $this->user, 1, $firstKey))->toThrow(ValidationException::class);
});

it('preserves the cut on editing disabling and reactivation', function (): void {
    $setting = activatePersonalMoneyNow($this->personal, $this->user);
    createActivationMovement($this->personal, $this->user);
    foreach ([false, true] as $enabled) {
        $updated = app(UpdatePersonalMoneySettings::class)->execute($this->user->id,
            new PersonalMoneySettingsDto($this->personal->id, '2026-10-01', 10000, $enabled));
        expect($updated->transaction_cutoff_id)->toBe($setting->transaction_cutoff_id)->and($updated->started_at->toDateString())->toBe('2026-10-11');
    }
    expect(app(BuildPersonalMoneySummary::class)->execute($this->user->id)['available_balance'])->toBe(9900.0);
});

it('excludes existing reimbursement snapshots even after their original ledger entry is deleted', function (): void {
    $entry = AccountMemberLedgerEntry::query()->create([
        'account_id' => $this->shared->id, 'user_id' => $this->user->id, 'related_user_id' => $this->partner->id,
        'type'       => 'settlement_transfer', 'amount' => 125, 'occurred_at' => '2026-10-11',
    ]);
    app(CaptureExistingPersonalMoneyReimbursements::class)->execute($this->user->id, '2026-10-01');
    $entry->delete();
    activatePersonalMoneyNow($this->personal, $this->user);
    expect(app(BuildPersonalMoneySummary::class)->execute($this->user->id)['history'])->toBe([]);
});
