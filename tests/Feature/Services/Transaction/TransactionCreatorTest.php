<?php

use App\Dto\TransactionFormDto;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Transaction\TransactionCreator;

it('creates a single transaction when no user payments are provided', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);
    $account->users()->attach($user->id, ['percentage' => 100]);
    $this->actingAs($user);

    $dto = TransactionFormDto::fromFormArray([
        'type' => TransactionType::Outcome,
        'status' => TransactionStatus::Completed,
        'concept' => 'Solo expense',
        'amount' => 120.0,
        'account_id' => $account->id,
        'split_between_users' => false,
        'user_payments' => [],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]);

    $transaction = app(TransactionCreator::class)->execute($dto);

    expect(Transaction::count())->toBe(1)
        ->and($transaction->amount)->toBe(120.0)
        ->and($transaction->type)->toBe(TransactionType::Outcome)
        ->and($transaction->status)->toBe(TransactionStatus::Completed);
});

it('creates sub transactions for outcome type with user payments', function () {
    $owner = User::factory()->create();
    $partner = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);
    $account->users()->sync([
        $owner->id => ['percentage' => 50],
        $partner->id => ['percentage' => 50],
    ]);
    $this->actingAs($owner);

    $dto = TransactionFormDto::fromFormArray([
        'type' => TransactionType::Outcome,
        'status' => TransactionStatus::Completed,
        'concept' => 'Shared expense',
        'amount' => 200.0,
        'account_id' => $account->id,
        'split_between_users' => true,
        'user_payments' => [
            ['user_id' => $owner->id, 'percentage' => 25],
            ['user_id' => $partner->id, 'percentage' => 75],
        ],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]);

    $mainTransaction = app(TransactionCreator::class)->execute($dto);

    $transactions = Transaction::orderBy('id')->get();
    $subTransactions = $transactions->where('id', '!=', $mainTransaction->id)->values();

    expect($transactions)->toHaveCount(3)
        ->and($mainTransaction->status)->toBe(TransactionStatus::Completed)
        ->and($mainTransaction->percentage)->toBe(100.0)
        ->and($subTransactions->pluck('amount')->sort()->values()->all())->toBe([50.0, 150.0])
        ->and($subTransactions->pluck('type')->unique()->all())->toBe([TransactionType::Income])
        ->and($subTransactions->pluck('status')->unique()->all())->toBe([TransactionStatus::Pending])
        ->and($subTransactions->pluck('user_id')->sort()->values()->all())->toBe([$owner->id, $partner->id])
        ->and($subTransactions->pluck('percentage')->sort()->values()->all())->toBe([25.0, 75.0])
        ->and($subTransactions->pluck('parent_transaction_id')->unique()->values()->all())->toBe([$mainTransaction->id]);
});

it('Throws an exception when creating an income transaction with non-completed status', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);
    $account->users()->attach($user->id, ['percentage' => 100]);
    $this->actingAs($user);

    $dto = TransactionFormDto::fromFormArray([
        'type' => TransactionType::Income,
        'status' => TransactionStatus::Pending,
        'concept' => 'Invalid income',
        'amount' => 100.0,
        'account_id' => $account->id,
        'split_between_users' => false,
        'user_payments' => [],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]);

    app(TransactionCreator::class)->execute($dto);
})->throws(\InvalidArgumentException::class, 'Income transactions must have status Completed.');
