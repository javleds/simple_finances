<?php

use App\Dto\TransactionFormDto;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Transaction\TransactionCreator;
use App\Services\Transaction\TransactionRemover;

it('deletes pending sub transactions and detaches completed ones when removing parent', function () {
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
        'concept' => 'Shared expense to delete',
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
    $subTransactions = Transaction::where('parent_transaction_id', $mainTransaction->id)->orderBy('id')->get();

    $subTransactions->first()->status = TransactionStatus::Completed;
    $subTransactions->first()->save();

    app(TransactionRemover::class)->execute($mainTransaction);

    $remainingMain = Transaction::find($mainTransaction->id);
    $pendingSubs = Transaction::where('parent_transaction_id', $mainTransaction->id)->where('status', TransactionStatus::Pending)->get();
    $detachedCompleted = Transaction::whereNull('parent_transaction_id')->where('status', TransactionStatus::Completed)->get();

    expect($remainingMain)->toBeNull()
        ->and($pendingSubs)->toHaveCount(0)
        ->and($detachedCompleted)->toHaveCount(1);
});

it('deletes a transaction without sub transactions', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $transaction = Transaction::factory()->create([
        'type' => TransactionType::Income,
        'status' => TransactionStatus::Completed,
        'user_id' => $user->id,
        'account_id' => $account->id,
    ]);

    app(TransactionRemover::class)->execute($transaction);

    expect(Transaction::find($transaction->id))->toBeNull();
});
