<?php

use App\Enums\SharedTransactionNotificationBatchStatus;
use App\Enums\SharedTransactionNotificationAction;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\NotificationType;
use App\Models\SharedTransactionNotificationBatch;
use App\Models\SharedTransactionNotificationItem;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\SharedTransactionBatchChangedEmail;
use App\Services\SharedTransactions\ProcessSharedTransactionNotificationBatchesAction;
use Illuminate\Support\Facades\Notification;

it('sends grouped notifications and marks batch as sent', function () {
    config()->set('notifications.shared_transactions.mode', 'grouped');
    config()->set('notifications.shared_transactions.debounce_minutes', 5);

    Notification::fake();

    $recipient = User::factory()->create();
    $modifier = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $modifier->id]);

    $notificationType = NotificationType::factory()->create([
        'name' => NotificationType::MOVEMENTS_NOTIFICATION,
    ]);

    $recipient->notificationTypes()->sync([$notificationType->id]);
    $recipient->notificableAccounts()->sync([$account->id]);

    $transaction = Transaction::factory()->create([
        'user_id' => $modifier->id,
        'account_id' => $account->id,
        'type' => TransactionType::Outcome,
        'status' => TransactionStatus::Completed,
        'concept' => 'Dinner',
        'amount' => 90.0,
        'scheduled_at' => now()->subDays(1),
    ]);

    $batch = SharedTransactionNotificationBatch::create([
        'user_id' => $recipient->id,
        'account_id' => $account->id,
        'status' => SharedTransactionNotificationBatchStatus::Pending,
        'window_started_at' => now()->subMinutes(10),
        'last_activity_at' => now()->subMinutes(10),
    ]);

    SharedTransactionNotificationItem::create([
        'batch_id' => $batch->id,
        'transaction_id' => $transaction->id,
        'modifier_id' => $modifier->id,
        'action' => SharedTransactionNotificationAction::Created,
        'concept' => $transaction->concept,
        'type' => $transaction->type,
        'amount' => $transaction->amount,
        'scheduled_at' => $transaction->scheduled_at,
    ]);

    app(ProcessSharedTransactionNotificationBatchesAction::class)->execute();

    Notification::assertSentTo($recipient, SharedTransactionBatchChangedEmail::class);

    $batch->refresh();

    expect($batch->status)->toBe(SharedTransactionNotificationBatchStatus::Sent)
        ->and($batch->sent_at)->not->toBeNull();
});
