<?php

use App\Dto\SharedTransactionNotificationDto;
use App\Enums\Action;
use App\Enums\SharedTransactionNotificationBatchStatus;
use App\Enums\SharedTransactionNotificationAction;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\SharedTransactionNotificationBatch;
use App\Models\SharedTransactionNotificationItem;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SharedTransactions\RegisterSharedTransactionNotificationAction;

it('creates a notification batch and item for shared transactions', function () {
    $recipient = User::factory()->create();
    $modifier = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $modifier->id]);

    $transaction = Transaction::factory()->create([
        'user_id' => $modifier->id,
        'account_id' => $account->id,
        'type' => TransactionType::Outcome,
        'status' => TransactionStatus::Completed,
        'concept' => 'Groceries',
        'amount' => 120.5,
        'scheduled_at' => now(),
    ]);

    $dto = new SharedTransactionNotificationDto(
        recipient: $recipient,
        modifier: $modifier,
        transaction: $transaction,
        action: Action::Created,
    );

    $batch = app(RegisterSharedTransactionNotificationAction::class)->execute($dto);

    $item = SharedTransactionNotificationItem::first();

    expect(SharedTransactionNotificationBatch::count())->toBe(1)
        ->and($batch->status)->toBe(SharedTransactionNotificationBatchStatus::Pending)
        ->and($batch->user_id)->toBe($recipient->id)
        ->and($batch->account_id)->toBe($account->id)
        ->and($item)->not->toBeNull()
        ->and($item->batch_id)->toBe($batch->id)
        ->and($item->modifier_id)->toBe($modifier->id)
        ->and($item->action)->toBe(SharedTransactionNotificationAction::Created);
});

it('reuses a pending batch and updates last activity', function () {
    $recipient = User::factory()->create();
    $modifier = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $modifier->id]);

    $firstTransaction = Transaction::factory()->create([
        'user_id' => $modifier->id,
        'account_id' => $account->id,
        'type' => TransactionType::Outcome,
        'status' => TransactionStatus::Completed,
        'concept' => 'Coffee',
        'amount' => 20.0,
        'scheduled_at' => now(),
    ]);

    $firstDto = new SharedTransactionNotificationDto(
        recipient: $recipient,
        modifier: $modifier,
        transaction: $firstTransaction,
        action: Action::Created,
    );

    $batch = app(RegisterSharedTransactionNotificationAction::class)->execute($firstDto);
    $firstLastActivity = $batch->last_activity_at;

    $secondTransaction = Transaction::factory()->create([
        'user_id' => $modifier->id,
        'account_id' => $account->id,
        'type' => TransactionType::Outcome,
        'status' => TransactionStatus::Completed,
        'concept' => 'Lunch',
        'amount' => 50.0,
        'scheduled_at' => now(),
    ]);

    $secondDto = new SharedTransactionNotificationDto(
        recipient: $recipient,
        modifier: $modifier,
        transaction: $secondTransaction,
        action: Action::Created,
    );

    $updatedBatch = app(RegisterSharedTransactionNotificationAction::class)->execute($secondDto);

    expect(SharedTransactionNotificationBatch::count())->toBe(1)
        ->and(SharedTransactionNotificationItem::count())->toBe(2)
        ->and($updatedBatch->id)->toBe($batch->id)
        ->and($updatedBatch->last_activity_at->greaterThanOrEqualTo($firstLastActivity))->toBeTrue();
});
