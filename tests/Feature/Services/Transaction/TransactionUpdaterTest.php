<?php

use App\Dto\TransactionFormDto;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Transaction\TransactionCreator;
use App\Services\Transaction\TransactionUpdater;

it('creates sub transactions when enabling split on an existing outcome transaction', function () {
    $owner = User::factory()->create();
    $partner = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);
    $account->users()->sync([
        $owner->id => ['percentage' => 50],
        $partner->id => ['percentage' => 50],
    ]);
    $this->actingAs($owner);

    $createDto = TransactionFormDto::fromFormArray([
        'type' => TransactionType::Outcome,
        'status' => TransactionStatus::Completed,
        'concept' => 'Shared expense no split',
        'amount' => 180.0,
        'account_id' => $account->id,
        'split_between_users' => false,
        'user_payments' => [],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]);

    $mainTransaction = app(TransactionCreator::class)->execute($createDto);

    $updateDto = TransactionFormDto::fromFormArray([
        'id' => $mainTransaction->id,
        'type' => TransactionType::Outcome,
        'status' => TransactionStatus::Completed,
        'concept' => 'Shared expense no split',
        'amount' => 180.0,
        'account_id' => $account->id,
        'split_between_users' => true,
        'user_payments' => [
            ['user_id' => $owner->id, 'percentage' => 40],
            ['user_id' => $partner->id, 'percentage' => 60],
        ],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]);

    $updatedTransaction = app(TransactionUpdater::class)->execute($mainTransaction, $updateDto);
    $subTransactions = Transaction::where('parent_transaction_id', $updatedTransaction->id)->orderBy('id')->get();

    expect($updatedTransaction->status)->toBe(TransactionStatus::Completed)
        ->and($subTransactions)->toHaveCount(2)
        ->and($subTransactions->pluck('type')->unique()->values()->all())->toBe([TransactionType::Income])
        ->and($subTransactions->pluck('status')->unique()->values()->all())->toBe([TransactionStatus::Pending])
        ->and($subTransactions->pluck('user_id')->values()->all())->toBe([$owner->id, $partner->id])
        ->and($subTransactions->pluck('amount')->values()->all())->toBe([72.0, 108.0])
        ->and($subTransactions->pluck('percentage')->values()->all())->toBe([40.0, 60.0])
        ->and($subTransactions->pluck('concept')->values()->all())->toBe([
            'Shared expense no split - Parte de '.$owner->name,
            'Shared expense no split - Parte de '.$partner->name,
        ]);
});

it('rebalances sub transactions when amount changes', function () {
    $owner = User::factory()->create();
    $partner = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);
    $account->users()->sync([
        $owner->id => ['percentage' => 50],
        $partner->id => ['percentage' => 50],
    ]);
    $this->actingAs($owner);

    $createDto = TransactionFormDto::fromFormArray([
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

    $mainTransaction = app(TransactionCreator::class)->execute($createDto);

    $updateDto = TransactionFormDto::fromFormArray([
        'id' => $mainTransaction->id,
        'type' => TransactionType::Outcome,
        'status' => TransactionStatus::Completed,
        'concept' => 'Shared expense updated',
        'amount' => 300.0,
        'account_id' => $account->id,
        'split_between_users' => true,
        'user_payments' => [
            ['user_id' => $owner->id, 'percentage' => 30],
            ['user_id' => $partner->id, 'percentage' => 70],
        ],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]);

    $updatedTransaction = app(TransactionUpdater::class)->execute($mainTransaction, $updateDto);
    $subTransactions = Transaction::where('parent_transaction_id', $updatedTransaction->id)->orderBy('id')->get();

    expect($updatedTransaction->amount)->toBe(300.0)
        ->and($subTransactions)->toHaveCount(2)
        ->and($subTransactions->pluck('amount')->values()->all())->toBe([90.0, 210.0])
        ->and($subTransactions->pluck('percentage')->values()->all())->toBe([30.0, 70.0])
        ->and($subTransactions->pluck('status')->unique()->all())->toBe([TransactionStatus::Pending])
        ->and($subTransactions->pluck('parent_transaction_id')->unique()->values()->all())->toBe([$updatedTransaction->id]);
});

it('removes pending sub transactions and detaches completed ones on type change', function () {
    $owner = User::factory()->create();
    $partner = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);
    $account->users()->sync([
        $owner->id => ['percentage' => 60],
        $partner->id => ['percentage' => 40],
    ]);
    $this->actingAs($owner);

    $createDto = TransactionFormDto::fromFormArray([
        'type' => TransactionType::Outcome,
        'status' => TransactionStatus::Completed,
        'concept' => 'Shared expense type change',
        'amount' => 150.0,
        'account_id' => $account->id,
        'split_between_users' => true,
        'user_payments' => [
            ['user_id' => $owner->id, 'percentage' => 60],
            ['user_id' => $partner->id, 'percentage' => 40],
        ],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]);

    $mainTransaction = app(TransactionCreator::class)->execute($createDto);
    $subTransactions = Transaction::where('parent_transaction_id', $mainTransaction->id)->orderBy('id')->get();

    $subTransactions->first()->status = TransactionStatus::Completed;
    $subTransactions->first()->save();

    $updateDto = TransactionFormDto::fromFormArray([
        'id' => $mainTransaction->id,
        'type' => TransactionType::Income,
        'status' => TransactionStatus::Completed,
        'concept' => 'Shared expense type change',
        'amount' => 150.0,
        'account_id' => $account->id,
        'split_between_users' => false,
        'user_payments' => [],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]);

    $updatedTransaction = app(TransactionUpdater::class)->execute($mainTransaction, $updateDto);

    $remainingSubTransactions = Transaction::where('parent_transaction_id', $updatedTransaction->id)->get();
    $detachedSubTransactions = Transaction::whereNull('parent_transaction_id')->whereIn('id', $subTransactions->pluck('id'))->get();

    expect($updatedTransaction->type)->toBe(TransactionType::Income)
        ->and($remainingSubTransactions)->toHaveCount(0)
        ->and($detachedSubTransactions)->toHaveCount(1)
        ->and($detachedSubTransactions->first()->status)->toBe(TransactionStatus::Completed);
});

it('deletes all pending sub transactions when changing outcome to income', function () {
    $owner = User::factory()->create();
    $partner = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);
    $account->users()->sync([
        $owner->id => ['percentage' => 50],
        $partner->id => ['percentage' => 50],
    ]);
    $this->actingAs($owner);

    $createDto = TransactionFormDto::fromFormArray([
        'type' => TransactionType::Outcome,
        'status' => TransactionStatus::Completed,
        'concept' => 'Outcome to income',
        'amount' => 100.0,
        'account_id' => $account->id,
        'split_between_users' => true,
        'user_payments' => [
            ['user_id' => $owner->id, 'percentage' => 50],
            ['user_id' => $partner->id, 'percentage' => 50],
        ],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]);

    $mainTransaction = app(TransactionCreator::class)->execute($createDto);

    $updateDto = TransactionFormDto::fromFormArray([
        'id' => $mainTransaction->id,
        'type' => TransactionType::Income,
        'status' => TransactionStatus::Completed,
        'concept' => 'Outcome to income',
        'amount' => 100.0,
        'account_id' => $account->id,
        'split_between_users' => false,
        'user_payments' => [],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]);

    $updatedTransaction = app(TransactionUpdater::class)->execute($mainTransaction, $updateDto);

    $subTransactions = Transaction::where('parent_transaction_id', $updatedTransaction->id)->get();

    expect($updatedTransaction->type)->toBe(TransactionType::Income)
        ->and($subTransactions)->toHaveCount(0)
        ->and(Transaction::where('concept', 'Outcome to income - Parte de '.$owner->name)->exists())->toBeFalse()
        ->and(Transaction::where('concept', 'Outcome to income - Parte de '.$partner->name)->exists())->toBeFalse();
});
