<?php

declare(strict_types=1);

use App\Notifications\SharedTransactionBatchChangedEmail;
use App\Services\SharedTransactions\ProcessSharedTransactionNotificationBatchesAction;
use FinSi\FinanceDomain\Enums\SharedTransactionNotificationAction;
use FinSi\FinanceDomain\Enums\SharedTransactionNotificationBatchStatus;
use FinSi\FinanceDomain\Enums\TransactionStatus;
use FinSi\FinanceDomain\Enums\TransactionType;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\AccountMemberLedgerEntry;
use FinSi\FinanceDomain\Models\NotificationType;
use FinSi\FinanceDomain\Models\SharedTransactionNotificationBatch;
use FinSi\FinanceDomain\Models\SharedTransactionNotificationItem;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Models\User;
use Illuminate\Support\Facades\Notification;

it('sends grouped notifications and marks batch as sent', function () {
    config()->set('app.spa_url', 'https://spa.example.test');
    config()->set('notifications.shared_transactions.mode', 'grouped');
    config()->set('notifications.shared_transactions.debounce_minutes', 5);

    Notification::fake();

    $recipient = User::factory()->create();
    $modifier = User::factory()->create();
    $account = Account::factory()->create([
        'name'    => 'Home',
        'user_id' => $modifier->id,
    ]);
    $otherAccount = Account::factory()->create([
        'name'    => 'Trip',
        'user_id' => $modifier->id,
    ]);
    $account->users()->sync([$recipient->id => ['percentage' => 50], $modifier->id => ['percentage' => 50]]);
    $otherAccount->users()->sync([$recipient->id => ['percentage' => 50], $modifier->id => ['percentage' => 50]]);

    $notificationType = NotificationType::factory()->create([
        'name' => NotificationType::MOVEMENTS_NOTIFICATION,
    ]);

    $recipient->notificationTypes()->sync([$notificationType->id]);
    $recipient->notificableAccounts()->sync([$account->id, $otherAccount->id]);

    $transaction = Transaction::factory()->create([
        'user_id'      => $modifier->id,
        'account_id'   => $account->id,
        'type'         => TransactionType::Outcome,
        'status'       => TransactionStatus::Completed,
        'concept'      => 'Dinner',
        'amount'       => '90.00',
        'scheduled_at' => now()->subDays(1),
    ]);
    $otherTransaction = Transaction::factory()->create([
        'user_id'      => $modifier->id,
        'account_id'   => $otherAccount->id,
        'type'         => TransactionType::Income,
        'status'       => TransactionStatus::Completed,
        'concept'      => 'Refund',
        'amount'       => '40.00',
        'scheduled_at' => now()->subDays(1),
    ]);

    AccountMemberLedgerEntry::query()->create([
        'account_id'      => $account->id,
        'user_id'         => $recipient->id,
        'transaction_id'  => $transaction->id,
        'related_user_id' => $modifier->id,
        'type'            => FinSi\FinanceDomain\Enums\AccountMemberLedgerEntryType::ExpenseShare,
        'amount'          => '-45.00',
        'description'     => 'Dinner',
        'occurred_at'     => $transaction->scheduled_at,
    ]);
    AccountMemberLedgerEntry::query()->create([
        'account_id'     => $account->id,
        'user_id'        => $modifier->id,
        'transaction_id' => $transaction->id,
        'type'           => FinSi\FinanceDomain\Enums\AccountMemberLedgerEntryType::ExpensePaid,
        'amount'         => '90.00',
        'description'    => 'Dinner',
        'occurred_at'    => $transaction->scheduled_at,
    ]);
    AccountMemberLedgerEntry::query()->create([
        'account_id'     => $otherAccount->id,
        'user_id'        => $recipient->id,
        'transaction_id' => $otherTransaction->id,
        'type'           => FinSi\FinanceDomain\Enums\AccountMemberLedgerEntryType::ExpensePaid,
        'amount'         => '50.00',
        'description'    => 'Refund',
        'occurred_at'    => $otherTransaction->scheduled_at,
    ]);
    AccountMemberLedgerEntry::query()->create([
        'account_id'      => $otherAccount->id,
        'user_id'         => $modifier->id,
        'transaction_id'  => $otherTransaction->id,
        'related_user_id' => $recipient->id,
        'type'            => FinSi\FinanceDomain\Enums\AccountMemberLedgerEntryType::ExpenseShare,
        'amount'          => '-25.00',
        'description'     => 'Refund',
        'occurred_at'     => $otherTransaction->scheduled_at,
    ]);

    $batch = SharedTransactionNotificationBatch::create([
        'user_id'           => $recipient->id,
        'account_id'        => $account->id,
        'group_key'         => "shared-movements:user:{$recipient->id}",
        'status'            => SharedTransactionNotificationBatchStatus::Pending,
        'window_started_at' => now()->subMinutes(10),
        'last_activity_at'  => now()->subMinutes(10),
    ]);

    SharedTransactionNotificationItem::create([
        'batch_id'       => $batch->id,
        'account_id'     => $account->id,
        'transaction_id' => $transaction->id,
        'modifier_id'    => $modifier->id,
        'action'         => SharedTransactionNotificationAction::Created,
        'concept'        => $transaction->concept,
        'type'           => $transaction->type,
        'amount'         => $transaction->amount,
        'scheduled_at'   => $transaction->scheduled_at,
    ]);
    SharedTransactionNotificationItem::create([
        'batch_id'       => $batch->id,
        'account_id'     => $otherAccount->id,
        'transaction_id' => $otherTransaction->id,
        'modifier_id'    => $modifier->id,
        'action'         => SharedTransactionNotificationAction::Updated,
        'concept'        => $otherTransaction->concept,
        'type'           => $otherTransaction->type,
        'amount'         => $otherTransaction->amount,
        'scheduled_at'   => $otherTransaction->scheduled_at,
    ]);
    SharedTransactionNotificationItem::create([
        'batch_id'       => $batch->id,
        'account_id'     => $account->id,
        'transaction_id' => null,
        'modifier_id'    => $modifier->id,
        'action'         => SharedTransactionNotificationAction::Settled,
        'concept'        => 'Pago de pendientes',
        'type'           => TransactionType::Income,
        'amount'         => '25.00',
        'scheduled_at'   => now()->subDay(),
    ]);

    app(ProcessSharedTransactionNotificationBatchesAction::class)->execute();

    Notification::assertSentTo(
        $recipient,
        SharedTransactionBatchChangedEmail::class,
        function (SharedTransactionBatchChangedEmail $notification) use ($recipient): bool {
            $mail = $notification->toMail($recipient);

            return 'https://spa.example.test/accounts' === $mail->viewData['link']
                && str_contains((string) $mail->render(), 'Resumen general')
                && str_contains((string) $mail->render(), 'Reembolso')
                && 2 === $mail->viewData['globalSummary']['accounts_count']
                && 3 === $mail->viewData['globalSummary']['movements_count']
                && '40.00' === $mail->viewData['globalSummary']['income_total']
                && '90.00' === $mail->viewData['globalSummary']['outcome_total']
                && collect($mail->viewData['accountsSummary'])->pluck('account_name')->sort()->values()->all() === ['Home', 'Trip']
                && '45.00' === collect($mail->viewData['accountsSummary'])->where('account_name', 'Home')->first()['por_pagar']
                && '25.00' === collect($mail->viewData['accountsSummary'])->where('account_name', 'Trip')->first()['por_recibir'];
        },
    );

    $batch->refresh();

    expect($batch->status)->toBe(SharedTransactionNotificationBatchStatus::Sent)
        ->and($batch->group_key)->toBeNull()
        ->and($batch->sent_at)->not->toBeNull();
});

it('excludes movements for accounts the recipient no longer notifies', function () {
    config()->set('app.spa_url', 'https://spa.example.test');
    config()->set('notifications.shared_transactions.mode', 'grouped');
    config()->set('notifications.shared_transactions.debounce_minutes', 5);

    Notification::fake();

    $recipient = User::factory()->create();
    $modifier = User::factory()->create();
    $notifiedAccount = Account::factory()->create(['name' => 'Visible account', 'user_id' => $modifier->id]);
    $mutedAccount = Account::factory()->create(['name' => 'Muted account', 'user_id' => $modifier->id]);
    $notifiedAccount->users()->sync([$recipient->id => ['percentage' => 50], $modifier->id => ['percentage' => 50]]);
    $mutedAccount->users()->sync([$recipient->id => ['percentage' => 50], $modifier->id => ['percentage' => 50]]);

    $notificationType = NotificationType::factory()->create([
        'name' => NotificationType::MOVEMENTS_NOTIFICATION,
    ]);

    $recipient->notificationTypes()->sync([$notificationType->id]);
    $recipient->notificableAccounts()->sync([$notifiedAccount->id]);

    $visibleTransaction = Transaction::factory()->create([
        'user_id'      => $modifier->id,
        'account_id'   => $notifiedAccount->id,
        'type'         => TransactionType::Outcome,
        'status'       => TransactionStatus::Completed,
        'concept'      => 'Visible dinner',
        'amount'       => '90.00',
        'scheduled_at' => now()->subDays(1),
    ]);
    $mutedTransaction = Transaction::factory()->create([
        'user_id'      => $modifier->id,
        'account_id'   => $mutedAccount->id,
        'type'         => TransactionType::Outcome,
        'status'       => TransactionStatus::Completed,
        'concept'      => 'Muted dinner',
        'amount'       => '120.00',
        'scheduled_at' => now()->subDays(1),
    ]);

    $batch = SharedTransactionNotificationBatch::create([
        'user_id'           => $recipient->id,
        'account_id'        => $notifiedAccount->id,
        'group_key'         => "shared-movements:user:{$recipient->id}",
        'status'            => SharedTransactionNotificationBatchStatus::Pending,
        'window_started_at' => now()->subMinutes(10),
        'last_activity_at'  => now()->subMinutes(10),
    ]);

    foreach ([[$visibleTransaction, $notifiedAccount], [$mutedTransaction, $mutedAccount]] as [$transaction, $account]) {
        SharedTransactionNotificationItem::create([
            'batch_id'       => $batch->id,
            'account_id'     => $account->id,
            'transaction_id' => $transaction->id,
            'modifier_id'    => $modifier->id,
            'action'         => SharedTransactionNotificationAction::Created,
            'concept'        => $transaction->concept,
            'type'           => $transaction->type,
            'amount'         => $transaction->amount,
            'scheduled_at'   => $transaction->scheduled_at,
        ]);
    }

    app(ProcessSharedTransactionNotificationBatchesAction::class)->execute();

    Notification::assertSentTo(
        $recipient,
        SharedTransactionBatchChangedEmail::class,
        function (SharedTransactionBatchChangedEmail $notification) use ($recipient): bool {
            $mail = $notification->toMail($recipient);

            return 1 === $mail->viewData['globalSummary']['accounts_count']
                && 1 === $mail->viewData['globalSummary']['movements_count']
                && collect($mail->viewData['accountsSummary'])->pluck('account_name')->all() === ['Visible account'];
        },
    );

    $batch->refresh();

    expect($batch->status)->toBe(SharedTransactionNotificationBatchStatus::Sent);
});
