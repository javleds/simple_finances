<?php

use App\Enums\Frequency;
use App\Enums\SharedTransactionNotificationBatchStatus;
use App\Enums\TransactionStatus;
use App\Models\NotificationType;
use App\Models\Account;
use App\Models\SharedTransactionNotificationBatch;
use App\Models\SharedTransactionNotificationItem;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\SharedTransactionBatchChangedEmail;
use App\Notifications\SharedTransactionChangedEmail;
use App\Services\Auth\JwtTokenService;
use App\Services\SharedTransactions\ProcessSharedTransactionNotificationBatchesAction;
use Illuminate\Support\Facades\Notification;

function dashboardApiHeaders(User $user): array
{
    $token = app(JwtTokenService::class)->generate($user)['token'];

    return [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer '.$token,
    ];
}

it('serves the dashboard endpoints and completes pending transactions through the api', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create([
        'name' => 'Nomina',
        'balance' => 100,
        'color' => '#6a4d4d',
        'user_id' => $user->id,
        'virtual' => false,
    ]);
    $virtualAccount = Account::factory()->create([
        'name' => 'Savings projection',
        'user_id' => $user->id,
        'virtual' => true,
    ]);
    $account->users()->attach($user->id);
    $virtualAccount->users()->attach($user->id);

    $transaction = Transaction::factory()->income()->pending()->create([
        'concept' => 'Pending reimbursement',
        'amount' => 450.5,
        'account_id' => $account->id,
        'user_id' => $user->id,
        'scheduled_at' => '2026-06-06',
    ]);

    Subscription::factory()->create([
        'amount' => 100,
        'frequency_type' => Frequency::Month,
        'frequency_unit' => 1,
        'finished_at' => null,
        'user_id' => $user->id,
    ]);

    $headers = dashboardApiHeaders($user);

    $this->withHeaders($headers)
        ->getJson('/api/dashboard/graph')
        ->assertOk()
        ->assertJsonPath('data.0.account_id', $account->id)
        ->assertJsonPath('data.0.account_name', 'Nomina')
        ->assertJsonPath('data.0.balance', 100.0)
        ->assertJsonPath('data.0.color', '#6a4d4d');

    $this->withHeaders($headers)
        ->getJson('/api/dashboard/accounts')
        ->assertOk()
        ->assertJsonPath('data.summary.active_accounts', 1)
        ->assertJsonPath('data.summary.virtual_accounts', 1)
        ->assertJsonPath('data.summary.shared_accounts', 0)
        ->assertJsonPath('data.summary.pending_total', 450.5)
        ->assertJsonPath('data.pending_actions.0.id', 'tx-'.$transaction->id)
        ->assertJsonPath('data.pending_actions.0.date', '2026-06-06');

    $this->withHeaders($headers)
        ->getJson('/api/dashboard/subscriptions')
        ->assertOk()
        ->assertJsonPath('data.annual_total', 1200.0)
        ->assertJsonPath('data.subscriptions_count', 1);

    $this->withHeaders($headers)
        ->postJson('/api/batch/transactions', [
            'action' => 'complete',
            'transaction_ids' => ['tx-'.$transaction->id],
        ])
        ->assertOk()
        ->assertJsonPath('data.processed', 1)
        ->assertJsonPath('data.failed', [])
        ->assertJsonPath('data.transaction_ids.0', 'tx-'.$transaction->id);

    expect($transaction->fresh()->status)->toBe(TransactionStatus::Completed);
});

it('queues shared account movement notifications from api endpoints until the grouped debounce window expires', function () {
    config()->set('notifications.shared_transactions.mode', 'grouped');
    config()->set('notifications.shared_transactions.debounce_minutes', 5);
    Notification::fake();

    $modifier = User::factory()->create();
    $recipient = User::factory()->create();
    $notificationType = NotificationType::factory()->create([
        'name' => NotificationType::MOVEMENTS_NOTIFICATION,
    ]);
    $account = Account::factory()->create([
        'user_id' => $modifier->id,
    ]);
    $account->users()->attach([$modifier->id, $recipient->id]);
    $recipient->notificationTypes()->attach($notificationType->id);
    $recipient->notificableAccounts()->attach($account->id);

    $headers = dashboardApiHeaders($modifier);

    $firstResponse = $this->withHeaders($headers)
        ->postJson("/api/accounts/{$account->id}/transactions", [
            'type' => 'outcome',
            'status' => 'completed',
            'concept' => 'Groceries',
            'amount' => 250,
            'split_between_users' => false,
            'scheduled_at' => '2026-06-06',
        ])
        ->assertCreated();

    $batch = SharedTransactionNotificationBatch::firstOrFail();
    $firstLastActivity = $batch->last_activity_at;

    $this->travel(2)->minutes();

    $this->withHeaders($headers)
        ->putJson("/api/accounts/{$account->id}/transactions/{$firstResponse->json('data.id')}", [
            'type' => 'outcome',
            'status' => 'completed',
            'concept' => 'Groceries updated',
            'amount' => 300,
            'split_between_users' => false,
            'scheduled_at' => '2026-06-06',
        ])
        ->assertOk();

    Notification::assertNotSentTo($recipient, SharedTransactionChangedEmail::class);

    expect(SharedTransactionNotificationBatch::count())->toBe(1)
        ->and(SharedTransactionNotificationItem::count())->toBe(2);

    $batch->refresh();

    expect($batch->status)->toBe(SharedTransactionNotificationBatchStatus::Pending)
        ->and($batch->last_activity_at->greaterThan($firstLastActivity))->toBeTrue();

    $this->travel(4)->minutes();
    app(ProcessSharedTransactionNotificationBatchesAction::class)->execute();
    Notification::assertNotSentTo($recipient, SharedTransactionBatchChangedEmail::class);

    $batch->refresh();
    expect($batch->status)->toBe(SharedTransactionNotificationBatchStatus::Pending);

    $this->travel(1)->minute();
    app(ProcessSharedTransactionNotificationBatchesAction::class)->execute();

    Notification::assertSentTo($recipient, SharedTransactionBatchChangedEmail::class);

    $batch->refresh();
    expect($batch->status)->toBe(SharedTransactionNotificationBatchStatus::Sent)
        ->and($batch->sent_at)->not->toBeNull();
});

it('queues shared account notifications when dashboard batch completes pending transactions', function () {
    config()->set('notifications.shared_transactions.mode', 'grouped');
    config()->set('notifications.shared_transactions.debounce_minutes', 5);
    Notification::fake();

    $recipient = User::factory()->create();
    $modifier = User::factory()->create();
    $notificationType = NotificationType::factory()->create([
        'name' => NotificationType::MOVEMENTS_NOTIFICATION,
    ]);
    $account = Account::factory()->create([
        'user_id' => $recipient->id,
    ]);
    $account->users()->attach([$recipient->id, $modifier->id]);
    $recipient->notificationTypes()->attach($notificationType->id);
    $recipient->notificableAccounts()->attach($account->id);

    $transaction = Transaction::factory()->income()->pending()->create([
        'concept' => 'Pending reimbursement',
        'amount' => 125,
        'account_id' => $account->id,
        'user_id' => $modifier->id,
        'scheduled_at' => '2026-06-06',
    ]);

    $this->withHeaders(dashboardApiHeaders($modifier))
        ->postJson('/api/batch/transactions', [
            'action' => 'complete',
            'transaction_ids' => ['tx-'.$transaction->id],
        ])
        ->assertOk()
        ->assertJsonPath('data.processed', 1);

    Notification::assertNotSentTo($recipient, SharedTransactionChangedEmail::class);
    Notification::assertNotSentTo($recipient, SharedTransactionBatchChangedEmail::class);

    $batch = SharedTransactionNotificationBatch::firstOrFail();

    expect($transaction->fresh()->status)->toBe(TransactionStatus::Completed)
        ->and($batch->status)->toBe(SharedTransactionNotificationBatchStatus::Pending)
        ->and($batch->user_id)->toBe($recipient->id)
        ->and($batch->account_id)->toBe($account->id)
        ->and(SharedTransactionNotificationItem::count())->toBe(1);
});
