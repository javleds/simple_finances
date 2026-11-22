<?php

declare(strict_types=1);

use App\Services\VirtualAccounts\BuildVirtualAccountSummary;
use App\Services\VirtualAccounts\CaptureVirtualAccountBalanceSnapshot;
use FinSi\FinanceDomain\Enums\TransactionType;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Models\User;
use FinSi\FinanceDomain\Money\Decimal;

it('creates a positive observed yield adjustment and updates the virtual account balance', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $account = Account::factory()->create([
        'user_id'     => $user->id,
        'virtual'     => true,
        'credit_card' => false,
    ]);
    $account->users()->attach($user->id);
    Transaction::factory()->income()->create([
        'account_id'   => $account->id,
        'user_id'      => $user->id,
        'amount'       => '10000.00',
        'scheduled_at' => '2026-08-01',
    ]);
    app(App\Services\Accounts\RecalculateAccountBalance::class)->execute($account)->balance;

    $snapshot = app(CaptureVirtualAccountBalanceSnapshot::class)->create(
        account: $account,
        userId: $user->id,
        observedBalance: '10300.00',
        observedAt: '2026-08-02',
        notes: 'Monthly statement',
    );

    expect($snapshot->previous_balance)->toBe('10000.00')
        ->and($snapshot->observed_balance)->toBe('10300.00')
        ->and($snapshot->delta)->toBe('300.00')
        ->and($snapshot->adjustment_transaction_id)->not->toBeNull()
        ->and(Decimal::normalize($account->fresh()->balance))->toBe('10300.00');

    $transaction = Transaction::query()->findOrFail($snapshot->adjustment_transaction_id);

    expect($transaction->type)->toBe(TransactionType::Income)
        ->and(Decimal::normalize($transaction->amount))->toBe('300.00')
        ->and($transaction->account_balance_snapshot_id)->toBe($snapshot->id);
});

it('keeps manual withdrawals separate from observed yield in the virtual summary', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $account = Account::factory()->create([
        'user_id'     => $user->id,
        'virtual'     => true,
        'credit_card' => false,
    ]);
    $account->users()->attach($user->id);
    Transaction::factory()->income()->create([
        'account_id'   => $account->id,
        'user_id'      => $user->id,
        'amount'       => '10000.00',
        'scheduled_at' => '2026-08-01',
    ]);
    app(App\Services\Accounts\RecalculateAccountBalance::class)->execute($account)->balance;

    app(CaptureVirtualAccountBalanceSnapshot::class)->create(
        account: $account,
        userId: $user->id,
        observedBalance: '10300.00',
        observedAt: '2026-08-02',
    );

    Transaction::factory()->outcome()->create([
        'account_id'                  => $account->id,
        'user_id'                     => $user->id,
        'amount'                      => '5000.00',
        'scheduled_at'                => '2026-08-03',
        'account_balance_snapshot_id' => null,
    ]);
    app(App\Services\Accounts\RecalculateAccountBalance::class)->execute($account)->balance;

    $summary = app(BuildVirtualAccountSummary::class)->execute($user->id);
    $item = collect($summary['accounts'])->firstWhere('account_id', $account->id);

    expect(Decimal::normalize($account->fresh()->balance))->toBe('5300.00')
        ->and($item['initial_balance'])->toBe('10000.00')
        ->and($item['manual_withdrawals'])->toBe('5000.00')
        ->and($item['observed_yield'])->toBe('300.00')
        ->and($item['current_balance'])->toBe('5300.00');
});

it('rejects balance snapshots for physical accounts', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $account = Account::factory()->create([
        'user_id'     => $user->id,
        'virtual'     => false,
        'credit_card' => false,
    ]);
    $account->users()->attach($user->id);

    app(CaptureVirtualAccountBalanceSnapshot::class)->create(
        account: $account,
        userId: $user->id,
        observedBalance: '100.00',
        observedAt: '2026-08-02',
    );
})->throws(Symfony\Component\HttpKernel\Exception\HttpException::class);

it('uses the locked current balance when a caller reuses a stale account', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id, 'balance' => '0.00', 'virtual' => true, 'credit_card' => false]);
    $account->users()->sync([$user->id => ['percentage' => '100.00']]);
    $this->actingAs($user);
    $service = app(CaptureVirtualAccountBalanceSnapshot::class);
    $stale = $account->fresh();
    $service->create($account, $user->id, '100.00', now()->subMinutes(2));
    $snapshot = $service->create($stale, $user->id, '110.00', now()->subMinute());
    $unchanged = $service->create($stale, $user->id, '110.00', now());

    expect($snapshot->previous_balance)->toBe('100.00')
        ->and($snapshot->delta)->toBe('10.00')
        ->and($unchanged->delta)->toBe('0.00')
        ->and($unchanged->adjustment_transaction_id)->toBeNull()
        ->and($account->fresh()->balance)->toBe('110.00');
});

it('refreshes a stale snapshot before replacing and deleting its latest adjustment', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id, 'balance' => '0.00', 'virtual' => true, 'credit_card' => false]);
    $account->users()->sync([$user->id => ['percentage' => '100.00']]);
    $this->actingAs($user);
    $service = app(CaptureVirtualAccountBalanceSnapshot::class);
    $snapshot = $service->create($account, $user->id, '100.00', now());
    $stale = $snapshot->fresh();
    $service->update($snapshot, '110.00', now());
    $updated = $service->update($stale, '120.00', now());

    expect($account->fresh()->balance)->toBe('120.00')
        ->and(Transaction::query()->where('account_balance_snapshot_id', $snapshot->id)->count())->toBe(1);

    $service->delete($stale);

    expect($account->fresh()->balance)->toBe('0.00')
        ->and(Transaction::query()->where('account_balance_snapshot_id', $snapshot->id)->exists())->toBeFalse();
});

it('rejects editing or deleting a snapshot once a later observation exists', function (): void {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id, 'balance' => '0.00', 'virtual' => true, 'credit_card' => false]);
    $account->users()->sync([$user->id => ['percentage' => '100.00']]);
    $this->actingAs($user);
    $service = app(CaptureVirtualAccountBalanceSnapshot::class);
    $snapshot = $service->create($account, $user->id, '100.00', now()->subMinute());
    $service->create($account, $user->id, '110.00', now());

    expect(fn () => $service->update($snapshot, '120.00', now()))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class)
        ->and(fn () => $service->delete($snapshot))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class)
        ->and($account->fresh()->balance)->toBe('110.00')
        ->and(Transaction::query()->where('account_balance_snapshot_id', $snapshot->id)->exists())->toBeTrue();
});
