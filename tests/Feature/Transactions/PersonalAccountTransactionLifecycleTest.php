<?php

declare(strict_types=1);

use App\Dto\TransactionFormDto;
use App\Enums\AccountMemberLedgerEntryType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Transaction\TransactionCreator;
use App\Services\Transaction\ProcessTransactionSideEffects;
use App\Services\Transaction\TransactionRemover;
use App\Services\Transaction\TransactionUpdater;
use App\Services\Transaction\SyncAccountMemberLedger;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->member = User::factory()->create();
    $this->account = Account::factory()->create(['user_id' => $this->owner->id, 'balance' => 0]);
    $this->account->users()->sync([$this->owner->id => ['percentage' => 100]]);
    $this->actingAs($this->owner);
});

function personalTransactionPayload(Account $account, array $changes = []): TransactionFormDto
{
    return TransactionFormDto::fromFormArray(array_replace([
        'account_id' => $account->id, 'concept' => 'Personal expense', 'amount' => 30,
        'type' => 'outcome', 'status' => 'completed', 'scheduled_at' => '2026-10-10',
    ], $changes));
}

it('records complete personal expenses without shared obligations at every balance', function (float $income, float $expense, float $balance): void {
    if ($income > 0) {
        app(TransactionCreator::class)->execute(personalTransactionPayload($this->account, ['type' => 'income', 'amount' => $income]));
    }
    $transaction = app(TransactionCreator::class)->execute(personalTransactionPayload($this->account, [
        'amount' => $expense, 'payment_source' => 'member_out_of_pocket', 'paid_by_user_id' => $this->member->id,
        'split_between_users' => true, 'user_payments' => [['user_id' => $this->member->id, 'percentage' => 100]],
    ]));
    expect($transaction->uses_shared_account_rules)->toBeFalse()
        ->and($transaction->paid_by_user_id)->toBe($this->owner->id)
        ->and($transaction->payment_source->value)->toBe('account_fund')
        ->and($transaction->allocations()->count())->toBe(0)
        ->and($transaction->subTransactions()->count())->toBe(0)
        ->and($transaction->ledgerEntries()->count())->toBe(1)
        ->and($transaction->ledgerEntries()->first()->type)->toBe(AccountMemberLedgerEntryType::AccountFundExpense)
        ->and((float) $transaction->ledgerEntries()->sum('amount'))->toBe(-$expense)
        ->and($this->account->fresh()->balance)->toBe($balance);
})->with([[100.0, 30.0, 70.0], [30.0, 30.0, 0.0], [0.0, 30.0, -30.0], [20.0, 50.0, -30.0]]);

it('keeps personal snapshots and original payer when sharing later then editing and deleting', function (): void {
    $transaction = app(TransactionCreator::class)->execute(personalTransactionPayload($this->account));
    $this->account->users()->attach($this->member->id, ['percentage' => 50]);
    $updated = app(TransactionUpdater::class)->execute($transaction, personalTransactionPayload($this->account, ['amount' => 50, 'paid_by_user_id' => $this->member->id, 'payment_source' => 'member_out_of_pocket', 'split_between_users' => true, 'user_payments' => [['user_id' => $this->member->id, 'percentage' => 100]]]));
    expect($updated->uses_shared_account_rules)->toBeFalse()->and($updated->paid_by_user_id)->toBe($this->owner->id)->and($updated->allocations()->count())->toBe(0)->and($this->account->fresh()->balance)->toBe(-50.0);
    app(TransactionRemover::class)->execute($updated);
    expect($this->account->fresh()->balance)->toBe(0.0)->and($updated->ledgerEntries()->count())->toBe(0);
});

it('classifies new transactions by current membership while keeping previous shared obligations', function (): void {
    $this->account->users()->attach($this->member->id, ['percentage' => 50]);
    $shared = app(TransactionCreator::class)->execute(personalTransactionPayload($this->account, ['paid_by_user_id' => $this->owner->id, 'payment_source' => 'member_out_of_pocket', 'split_between_users' => true, 'user_payments' => [['user_id' => $this->owner->id, 'percentage' => 50], ['user_id' => $this->member->id, 'percentage' => 50]]]));
    $previousLedger = $shared->ledgerEntries()->get()->map->getAttributes()->all();
    expect($shared->uses_shared_account_rules)->toBeTrue()->and($shared->allocations()->count())->toBe(2);
    $this->account->users()->detach($this->member->id);
    $personal = app(TransactionCreator::class)->execute(personalTransactionPayload($this->account));
    expect($personal->uses_shared_account_rules)->toBeFalse()->and($shared->fresh()->uses_shared_account_rules)->toBeTrue()->and($shared->ledgerEntries()->get()->map->getAttributes()->all())->toBe($previousLedger);
    $updated = app(TransactionUpdater::class)->execute($shared, personalTransactionPayload($this->account, ['payment_source' => 'member_out_of_pocket']));
    expect($updated->uses_shared_account_rules)->toBeTrue();
});

it('reclassifies only when a transaction moves to a different account', function (): void {
    $sharedAccount = Account::factory()->create(['user_id' => $this->owner->id, 'balance' => 0]);
    $sharedAccount->users()->sync([$this->owner->id, $this->member->id]);
    $transaction = app(TransactionCreator::class)->execute(personalTransactionPayload($this->account));
    $moved = app(TransactionUpdater::class)->execute($transaction, personalTransactionPayload($sharedAccount, ['payment_source' => 'member_out_of_pocket', 'paid_by_user_id' => $this->member->id]));
    expect($this->account->fresh()->balance)->toBe(0.0)->and($sharedAccount->fresh()->balance)->toBe(-30.0)->and($moved->uses_shared_account_rules)->toBeTrue()->and($moved->paid_by_user_id)->toBe($this->member->id);
    $returned = app(TransactionUpdater::class)->execute($moved, personalTransactionPayload($this->account));
    expect($sharedAccount->fresh()->balance)->toBe(0.0)->and($this->account->fresh()->balance)->toBe(-30.0)->and($returned->uses_shared_account_rules)->toBeFalse()->and($returned->paid_by_user_id)->toBe($this->owner->id)->and($returned->ledgerEntries()->first()->type)->toBe(AccountMemberLedgerEntryType::AccountFundExpense);
});

it('preserves null legacy financial behavior and derives snapshots for new income and empty membership', function (): void {
    $legacy = Transaction::factory()->create(['account_id' => $this->account->id, 'user_id' => $this->owner->id, 'type' => 'outcome', 'amount' => 40, 'payment_source' => 'account_fund', 'uses_shared_account_rules' => null]);
    app(SyncAccountMemberLedger::class)->execute($legacy);
    expect($legacy->ledgerEntries()->where('type', AccountMemberLedgerEntryType::AccountDeficitShare)->exists())->toBeTrue();
    $updated = app(TransactionUpdater::class)->execute($legacy, personalTransactionPayload($this->account));
    expect($updated->uses_shared_account_rules)->toBeNull()->and($updated->ledgerEntries()->where('type', AccountMemberLedgerEntryType::AccountDeficitShare)->exists())->toBeTrue();
    $this->account->users()->detach();
    $income = app(TransactionCreator::class)->execute(personalTransactionPayload($this->account, ['type' => 'income', 'amount' => 50]));
    expect($income->uses_shared_account_rules)->toBeFalse()->and($income->custodian_user_id)->toBe($this->owner->id);
});

it('uses the locked persisted financial snapshot instead of stale caller attributes', function (): void {
    $transaction = app(TransactionCreator::class)->execute(personalTransactionPayload($this->account));
    $transaction->uses_shared_account_rules = true;
    $updated = app(TransactionUpdater::class)->execute($transaction, personalTransactionPayload($this->account, ['amount' => 80]));
    expect($updated->uses_shared_account_rules)->toBeFalse()->and((float) $updated->ledgerEntries()->sum('amount'))->toBe(-80.0);
});

it('refreshes the source account before a moved transaction notification can fail', function (): void {
    $transaction = app(TransactionCreator::class)->execute(personalTransactionPayload($this->account));
    $destination = Account::factory()->create(['user_id' => $this->owner->id, 'balance' => 0]);
    $destination->users()->sync([$this->owner->id => ['percentage' => 100]]);
    $this->mock(ProcessTransactionSideEffects::class)
        ->shouldReceive('execute')
        ->once()
        ->andThrow(new RuntimeException('Notification unavailable'));

    expect(fn () => app(TransactionUpdater::class)->execute($transaction, personalTransactionPayload($destination)))
        ->toThrow(RuntimeException::class, 'Notification unavailable');
    expect($transaction->fresh()->account_id)->toBe($destination->id)
        ->and($this->account->fresh()->balance)->toBe(0.0);
});
