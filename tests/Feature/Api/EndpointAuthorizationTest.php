<?php

use App\Enums\SharedTransactionNotificationAction;
use App\Enums\SharedTransactionNotificationBatchStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountUserNotification;
use App\Models\FixedIncome;
use App\Models\FixedOutcome;
use App\Models\FinancialGoal;
use App\Models\NotificationType;
use App\Models\PartialFixedIncome;
use App\Models\SharedTransactionNotificationBatch;
use App\Models\SharedTransactionNotificationItem;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\TelegramVerificationCode;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Auth\JwtTokenService;

function endpointAuthorizationHeaders(User $user): array
{
    $token = app(JwtTokenService::class)->generate($user)['token'];

    return [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer '.$token,
    ];
}

function endpointAuthorizationAccountFor(User $user): Account
{
    $account = Account::factory()->create(['user_id' => $user->id]);
    $account->users()->attach($user->id, ['percentage' => 100]);

    return $account;
}

it('forbids reading resources owned by another user without relying on global scopes', function (): void {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $account = endpointAuthorizationAccountFor($owner);
    $transaction = Transaction::factory()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
    ]);
    $financialGoal = FinancialGoal::factory()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
    ]);
    $fixedIncome = FixedIncome::factory()->create(['user_id' => $owner->id]);
    $fixedOutcome = FixedOutcome::factory()->create([
        'fixed_income_id' => $fixedIncome->id,
        'user_id' => $owner->id,
    ]);
    $partialFixedIncome = PartialFixedIncome::factory()->create([
        'fixed_income_id' => $fixedIncome->id,
        'user_id' => $owner->id,
    ]);
    $subscription = Subscription::factory()->create(['user_id' => $owner->id]);
    $subscriptionPayment = SubscriptionPayment::factory()->create([
        'subscription_id' => $subscription->id,
        'user_id' => $owner->id,
    ]);
    $telegramCode = TelegramVerificationCode::factory()->create(['user_id' => $owner->id]);
    $accountNotification = AccountUserNotification::factory()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
    ]);
    $batch = SharedTransactionNotificationBatch::query()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
        'status' => SharedTransactionNotificationBatchStatus::Pending,
        'window_started_at' => now(),
        'last_activity_at' => now(),
    ]);
    $item = SharedTransactionNotificationItem::query()->create([
        'batch_id' => $batch->id,
        'transaction_id' => $transaction->id,
        'modifier_id' => $owner->id,
        'action' => SharedTransactionNotificationAction::Created,
        'concept' => 'Foreign notification item',
        'type' => TransactionType::Income,
        'amount' => 100,
        'scheduled_at' => now(),
    ]);

    $this->withHeaders(endpointAuthorizationHeaders($intruder))->getJson("/api/accounts/{$account->id}")->assertForbidden();
    $this->withHeaders(endpointAuthorizationHeaders($intruder))->getJson("/api/transactions/{$transaction->id}")->assertForbidden();
    $this->withHeaders(endpointAuthorizationHeaders($intruder))->getJson("/api/financial-goals/{$financialGoal->id}")->assertForbidden();
    $this->withHeaders(endpointAuthorizationHeaders($intruder))->getJson("/api/fixed-incomes/{$fixedIncome->id}")->assertForbidden();
    $this->withHeaders(endpointAuthorizationHeaders($intruder))->getJson("/api/fixed-outcomes/{$fixedOutcome->id}")->assertForbidden();
    $this->withHeaders(endpointAuthorizationHeaders($intruder))->getJson("/api/partial-fixed-incomes/{$partialFixedIncome->id}")->assertForbidden();
    $this->withHeaders(endpointAuthorizationHeaders($intruder))->getJson("/api/subscriptions/{$subscription->id}")->assertForbidden();
    $this->withHeaders(endpointAuthorizationHeaders($intruder))->getJson("/api/subscription-payments/{$subscriptionPayment->id}")->assertForbidden();
    $this->withHeaders(endpointAuthorizationHeaders($intruder))->getJson("/api/telegram-verification-codes/{$telegramCode->id}")->assertForbidden();
    $this->withHeaders(endpointAuthorizationHeaders($intruder))->getJson("/api/account-user-notifications/{$accountNotification->id}")->assertForbidden();
    $this->withHeaders(endpointAuthorizationHeaders($intruder))->getJson("/api/shared-transaction-notification-batches/{$batch->id}")->assertForbidden();
    $this->withHeaders(endpointAuthorizationHeaders($intruder))->getJson("/api/shared-transaction-notification-items/{$item->id}")->assertForbidden();
});

it('forbids creating records linked to inaccessible accounts or parent resources', function (): void {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $account = endpointAuthorizationAccountFor($owner);
    $fixedIncome = FixedIncome::factory()->create(['user_id' => $owner->id]);
    $subscription = Subscription::factory()->create(['user_id' => $owner->id]);

    $headers = endpointAuthorizationHeaders($intruder);

    $this->withHeaders($headers)->postJson('/api/transactions', [
        'type' => TransactionType::Income->value,
        'status' => TransactionStatus::Completed->value,
        'concept' => 'Foreign account income',
        'amount' => 100,
        'account_id' => $account->id,
        'scheduled_at' => now()->toDateString(),
    ])->assertForbidden();

    $this->withHeaders($headers)->postJson('/api/financial-goals', [
        'account_id' => $account->id,
        'name' => 'Foreign account goal',
        'amount' => 1000,
    ])->assertForbidden();

    $this->withHeaders($headers)->postJson('/api/subscriptions', [
        'name' => 'Foreign feed subscription',
        'amount' => 100,
        'started_at' => now()->toDateString(),
        'frequency_unit' => 1,
        'frequency_type' => 'months',
        'feed_account_id' => $account->id,
    ])->assertForbidden();

    $this->withHeaders($headers)->postJson('/api/subscription-payments', [
        'subscription_id' => $subscription->id,
        'scheduled_at' => now()->toDateString(),
        'amount' => 100,
    ])->assertForbidden();

    $this->withHeaders($headers)->postJson('/api/fixed-outcomes', [
        'fixed_income_id' => $fixedIncome->id,
        'name' => 'Foreign fixed outcome',
        'amount' => 100,
        'type' => 'savings',
    ])->assertForbidden();

    $this->withHeaders($headers)->postJson('/api/partial-fixed-incomes', [
        'fixed_income_id' => $fixedIncome->id,
        'name' => 'Foreign partial income',
        'amount' => 100,
    ])->assertForbidden();
});

it('filters notification settings accounts to accounts visible by the authenticated user', function (): void {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $foreignAccount = endpointAuthorizationAccountFor($owner);
    $ownAccount = endpointAuthorizationAccountFor($intruder);
    $notificationType = NotificationType::factory()->create();

    $this->withHeaders(endpointAuthorizationHeaders($intruder))->putJson('/api/notification-settings', [
        'notification_type_ids' => [$notificationType->id],
        'account_ids' => [$foreignAccount->id, $ownAccount->id],
    ])->assertOk();

    expect($intruder->fresh()->notificableAccounts()->pluck('accounts.id')->all())
        ->toBe([$ownAccount->id]);
});
