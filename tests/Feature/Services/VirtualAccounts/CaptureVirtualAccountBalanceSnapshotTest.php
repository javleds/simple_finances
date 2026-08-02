<?php

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\VirtualAccounts\BuildVirtualAccountSummary;
use App\Services\VirtualAccounts\CaptureVirtualAccountBalanceSnapshot;

it('creates a positive observed yield adjustment and updates the virtual account balance', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $account = Account::factory()->create([
        'user_id' => $user->id,
        'virtual' => true,
        'credit_card' => false,
    ]);
    $account->users()->attach($user->id);
    Transaction::factory()->income()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
        'amount' => 10000.0,
        'scheduled_at' => '2026-08-01',
    ]);
    $account->updateBalance();

    $snapshot = app(CaptureVirtualAccountBalanceSnapshot::class)->create(
        account: $account,
        userId: $user->id,
        observedBalance: 10300.0,
        observedAt: '2026-08-02',
        notes: 'Monthly statement',
    );

    expect($snapshot->previous_balance)->toBe(10000.0)
        ->and($snapshot->observed_balance)->toBe(10300.0)
        ->and($snapshot->delta)->toBe(300.0)
        ->and($snapshot->adjustment_transaction_id)->not->toBeNull()
        ->and((float) $account->fresh()->balance)->toBe(10300.0);

    $transaction = Transaction::query()->findOrFail($snapshot->adjustment_transaction_id);

    expect($transaction->type)->toBe(TransactionType::Income)
        ->and((float) $transaction->amount)->toBe(300.0)
        ->and($transaction->account_balance_snapshot_id)->toBe($snapshot->id);
});

it('keeps manual withdrawals separate from observed yield in the virtual summary', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $account = Account::factory()->create([
        'user_id' => $user->id,
        'virtual' => true,
        'credit_card' => false,
    ]);
    $account->users()->attach($user->id);
    Transaction::factory()->income()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
        'amount' => 10000.0,
        'scheduled_at' => '2026-08-01',
    ]);
    $account->updateBalance();

    app(CaptureVirtualAccountBalanceSnapshot::class)->create(
        account: $account,
        userId: $user->id,
        observedBalance: 10300.0,
        observedAt: '2026-08-02',
    );

    Transaction::factory()->outcome()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
        'amount' => 5000.0,
        'scheduled_at' => '2026-08-03',
        'account_balance_snapshot_id' => null,
    ]);
    $account->updateBalance();

    $summary = app(BuildVirtualAccountSummary::class)->execute($user->id);
    $item = collect($summary['accounts'])->firstWhere('account_id', $account->id);

    expect((float) $account->fresh()->balance)->toBe(5300.0)
        ->and($item['initial_balance'])->toBe(10000.0)
        ->and($item['manual_withdrawals'])->toBe(5000.0)
        ->and($item['observed_yield'])->toBe(300.0)
        ->and($item['current_balance'])->toBe(5300.0);
});

it('rejects balance snapshots for physical accounts', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $account = Account::factory()->create([
        'user_id' => $user->id,
        'virtual' => false,
        'credit_card' => false,
    ]);
    $account->users()->attach($user->id);

    app(CaptureVirtualAccountBalanceSnapshot::class)->create(
        account: $account,
        userId: $user->id,
        observedBalance: 100.0,
        observedAt: '2026-08-02',
    );
})->throws(Symfony\Component\HttpKernel\Exception\HttpException::class);
