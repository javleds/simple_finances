<?php

declare(strict_types=1);

use App\Dto\TransactionFormDto;
use App\Services\Transaction\TransactionCreator;
use App\Services\Transaction\TransactionRemover;
use App\Services\Transaction\TransactionUpdater;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\AccountMemberLedgerEntry;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Models\User;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

beforeEach(function (): void {
    $this->moneyActor = User::factory()->create();
    $this->moneyAccount = Account::factory()->create([
        'user_id'          => $this->moneyActor->id,
        'balance'          => '0.00',
        'credit_card'      => false,
        'virtual'          => false,
        'credit_line'      => null,
        'spent'            => null,
        'available_credit' => null,
    ]);
    $this->moneyAccount->users()->sync([$this->moneyActor->id => ['percentage' => '100.00']]);
    $this->actingAs($this->moneyActor);
});

function exactLifecycleDto(Account $account, string $amount, string $type = 'income'): TransactionFormDto
{
    return TransactionFormDto::fromFormArray([
        'account_id'   => $account->id,
        'amount'       => $amount,
        'concept'      => 'Exact monetary movement',
        'type'         => $type,
        'status'       => 'completed',
        'scheduled_at' => now(),
    ]);
}

it('preserves cents through create edit and delete without stale ledger rows', function (): void {
    $creator = app(TransactionCreator::class);
    $creator->execute(exactLifecycleDto($this->moneyAccount, '0.30'));
    $expense = $creator->execute(exactLifecycleDto($this->moneyAccount, '0.29', 'outcome'));

    expect($this->moneyAccount->fresh()->balance)->toBe('0.01')
        ->and($expense->amount)->toBe('0.29');

    $updated = app(TransactionUpdater::class)->execute($expense, exactLifecycleDto($this->moneyAccount, '0.28', 'outcome'));
    expect($this->moneyAccount->fresh()->balance)->toBe('0.02')
        ->and($updated->ledgerEntries()->count())->toBe(1)
        ->and($updated->ledgerEntries()->first()->amount)->toBe('-0.28');

    app(TransactionRemover::class)->execute($updated);
    expect($this->moneyAccount->fresh()->balance)->toBe('0.30')
        ->and(AccountMemberLedgerEntry::query()->where('transaction_id', $expense->id)->exists())->toBeFalse();
});

it('rolls back the movement and ledger when the resulting account balance exceeds storage', function (): void {
    $creator = app(TransactionCreator::class);
    $creator->execute(exactLifecycleDto($this->moneyAccount, '99999999.99'));
    $transactionCount = Transaction::count();
    $entryCount = AccountMemberLedgerEntry::count();

    expect(fn () => $creator->execute(exactLifecycleDto($this->moneyAccount, '0.01')))
        ->toThrow(ValidationException::class);

    expect($this->moneyAccount->fresh()->balance)->toBe('99999999.99')
        ->and(Transaction::count())->toBe($transactionCount)
        ->and(AccountMemberLedgerEntry::count())->toBe($entryCount);
});

it('routes channel edits and deletion through the same exact ledger lifecycle', function (): void {
    $transaction = app(TransactionCreator::class)->execute(exactLifecycleDto($this->moneyAccount, '0.29', 'outcome'));
    $service = app(App\Services\Transaction\LastTransactionService::class);

    expect($service->modifyTransaction($transaction, ['amount' => '0.28'], $this->moneyActor))->toBeTrue()
        ->and($this->moneyAccount->fresh()->balance)->toBe('-0.28')
        ->and($transaction->fresh()->ledgerEntries()->firstOrFail()->amount)->toBe('-0.28');

    expect($service->deleteTransaction($transaction->fresh(), $this->moneyActor))->toBeTrue()
        ->and($this->moneyAccount->fresh()->balance)->toBe('0.00')
        ->and(AccountMemberLedgerEntry::query()->where('transaction_id', $transaction->id)->exists())->toBeFalse();
});

it('rejects a stale source account before moving a refreshed transaction again', function (): void {
    $second = Account::factory()->create(['user_id' => $this->moneyActor->id, 'balance' => '0.00', 'credit_card' => false, 'virtual' => false]);
    $third = Account::factory()->create(['user_id' => $this->moneyActor->id, 'balance' => '0.00', 'credit_card' => false, 'virtual' => false]);
    $second->users()->sync([$this->moneyActor->id => ['percentage' => '100.00']]);
    $third->users()->sync([$this->moneyActor->id => ['percentage' => '100.00']]);
    $transaction = app(TransactionCreator::class)->execute(exactLifecycleDto($this->moneyAccount, '10.01'));
    $stale = $transaction->fresh();
    app(TransactionUpdater::class)->execute($transaction, exactLifecycleDto($second, '10.01'));

    expect(fn () => app(TransactionUpdater::class)->execute($stale, exactLifecycleDto($third, '10.02')))
        ->toThrow(ConflictHttpException::class)
        ->and($second->fresh()->balance)->toBe('10.01')
        ->and($third->fresh()->balance)->toBe('0.00');

    $updated = app(TransactionUpdater::class)->execute($stale->fresh(), exactLifecycleDto($third, '10.02'));

    expect($updated->account_id)->toBe($third->id)
        ->and($this->moneyAccount->fresh()->balance)->toBe('0.00')
        ->and($second->fresh()->balance)->toBe('0.00')
        ->and($third->fresh()->balance)->toBe('10.02')
        ->and($updated->ledgerEntries()->firstOrFail()->account_id)->toBe($third->id);
});

it('rejects deleting a stale source account until the caller refreshes it', function (): void {
    $destination = Account::factory()->create(['user_id' => $this->moneyActor->id, 'balance' => '0.00', 'credit_card' => false, 'virtual' => false]);
    $destination->users()->sync([$this->moneyActor->id => ['percentage' => '100.00']]);
    $transaction = app(TransactionCreator::class)->execute(exactLifecycleDto($this->moneyAccount, '10.01'));
    $stale = $transaction->fresh();
    app(TransactionUpdater::class)->execute($transaction, exactLifecycleDto($destination, '10.02'));

    expect(fn () => app(TransactionRemover::class)->execute($stale))->toThrow(ConflictHttpException::class)
        ->and($destination->fresh()->balance)->toBe('10.02')
        ->and(Transaction::query()->whereKey($transaction->id)->exists())->toBeTrue();

    app(TransactionRemover::class)->execute($stale->fresh());

    expect($destination->fresh()->balance)->toBe('0.00')
        ->and($this->moneyAccount->fresh()->balance)->toBe('0.00')
        ->and(Transaction::query()->whereKey($transaction->id)->exists())->toBeFalse()
        ->and(AccountMemberLedgerEntry::query()->where('transaction_id', $transaction->id)->exists())->toBeFalse();
});

it('rejects a source account change discovered after choosing the account locks', function (string $operation): void {
    $destination = Account::factory()->create(['user_id' => $this->moneyActor->id, 'balance' => '0.00', 'credit_card' => false, 'virtual' => false]);
    $concurrentDestination = Account::factory()->create(['user_id' => $this->moneyActor->id, 'balance' => '0.00', 'credit_card' => false, 'virtual' => false]);
    $destination->users()->sync([$this->moneyActor->id => ['percentage' => '100.00']]);
    $concurrentDestination->users()->sync([$this->moneyActor->id => ['percentage' => '100.00']]);
    $transaction = app(TransactionCreator::class)->execute(exactLifecycleDto($this->moneyAccount, '10.01'));
    $moved = false;
    Illuminate\Support\Facades\DB::listen(function (Illuminate\Database\Events\QueryExecuted $query) use (&$moved, $transaction, $concurrentDestination): void {
        if ($moved || ! str_contains($query->sql, 'select `id`, `account_id` from `transactions`')) {
            return;
        }
        $moved = true;
        app(TransactionUpdater::class)->execute($transaction, exactLifecycleDto($concurrentDestination, '10.02'));
    });

    $mutate = 'update' === $operation
        ? fn () => app(TransactionUpdater::class)->execute($transaction, exactLifecycleDto($destination, '10.03'))
        : fn () => app(TransactionRemover::class)->execute($transaction);

    expect($mutate)->toThrow(ConflictHttpException::class)
        ->and($transaction->fresh()->account_id)->toBe($concurrentDestination->id)
        ->and($transaction->fresh()->amount)->toBe('10.02')
        ->and($destination->fresh()->balance)->toBe('0.00')
        ->and($concurrentDestination->fresh()->balance)->toBe('10.02');
})->with(['update', 'delete']);
