<?php

use App\Models\Account;
use App\Models\AccountInvite;
use App\Models\FixedIncome;
use App\Models\FixedOutcome;
use App\Models\NotificationType;
use App\Models\PartialFixedIncome;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\TelegramVerificationCode;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\InviteAccountEmail;
use App\Services\Auth\JwtTokenService;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

function apiHeaders(User $user): array
{
    $token = app(JwtTokenService::class)->generate($user)['token'];

    return [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer '.$token,
    ];
}

function seedNotificationTypes(): void
{
    NotificationType::factory()->create([
        'name' => NotificationType::INVITATION_NOTIFICATION,
        'description' => 'Invitation emails',
    ]);
    NotificationType::factory()->create([
        'name' => NotificationType::INVITATION_INTERACTION,
        'description' => 'Invitation responses',
    ]);
    NotificationType::factory()->create([
        'name' => NotificationType::MOVEMENTS_NOTIFICATION,
        'description' => 'Shared account movements',
    ]);
    NotificationType::factory()->create([
        'name' => NotificationType::WEEKLY_SUMMARY,
        'description' => 'Weekly summary',
    ]);
}

it('registers, logs in, shows profile and logs out', function () {
    Notification::fake();
    seedNotificationTypes();

    $registerResponse = $this->postJson('/api/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'phone_number' => '555-0000',
        'terms_accepted' => true,
        'privacy_policy_accepted' => true,
    ]);

    $registerResponse
        ->assertCreated()
        ->assertJsonPath('data.name', 'Jane Doe')
        ->assertJsonPath('data.email', 'jane@example.com')
        ->assertJsonPath('data.is_email_verified', false)
        ->assertJsonStructure(['meta' => ['auth' => ['token', 'expires_at', 'token_type']]]);

    $user = User::withoutGlobalScopes()->where('email', 'jane@example.com')->firstOrFail();

    Notification::assertSentTo($user, VerifyEmail::class);

    $loginResponse = $this->postJson('/api/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'password123',
    ]);

    $loginResponse
        ->assertOk()
        ->assertJsonPath('data.name', 'Jane Doe')
        ->assertJsonPath('data.email', 'jane@example.com')
        ->assertJsonPath('data.is_email_verified', false);

    $token = $loginResponse->json('meta.auth.token');

    $this->withHeaders([
        'Accept' => 'application/json',
        'Authorization' => 'Bearer '.$token,
    ])->getJson('/api/profile')
        ->assertOk()
        ->assertJsonPath('data.email', 'jane@example.com')
        ->assertJsonPath('data.is_email_verified', false);

    $this->withHeaders([
        'Accept' => 'application/json',
        'Authorization' => 'Bearer '.$token,
    ])->deleteJson('/api/auth/logout')
        ->assertOk();

    $this->withHeaders([
        'Accept' => 'application/json',
        'Authorization' => 'Bearer '.$token,
    ])->getJson('/api/profile')
        ->assertUnauthorized();
});

it('resends email verification by email when the account is pending verification', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create([
        'email' => 'pending@example.com',
    ]);

    $this->postJson('/api/auth/email-verification-notification-by-email', [
        'email' => $user->email,
    ])->assertOk()
        ->assertJsonPath('message', 'If the account exists and the email is not verified, a verification link will be sent.');

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('does not reveal whether the email exists or is already verified', function () {
    Notification::fake();

    $verifiedUser = User::factory()->create([
        'email' => 'verified@example.com',
        'email_verified_at' => now(),
    ]);

    $this->postJson('/api/auth/email-verification-notification-by-email', [
        'email' => $verifiedUser->email,
    ])->assertOk()
        ->assertJsonPath('message', 'If the account exists and the email is not verified, a verification link will be sent.');

    $this->postJson('/api/auth/email-verification-notification-by-email', [
        'email' => 'missing@example.com',
    ])->assertOk()
        ->assertJsonPath('message', 'If the account exists and the email is not verified, a verification link will be sent.');

    Notification::assertNotSentTo($verifiedUser, VerifyEmail::class);
});

it('rate limits email verification resend by email and ip', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create([
        'email' => 'limit@example.com',
    ]);

    for ($attempt = 0; $attempt < 3; $attempt++) {
        $this->postJson('/api/auth/email-verification-notification-by-email', [
            'email' => $user->email,
        ])->assertOk();
    }

    $this->postJson('/api/auth/email-verification-notification-by-email', [
        'email' => $user->email,
    ])->assertStatus(429);
});

it('updates notification settings', function () {
    seedNotificationTypes();
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);
    $account->users()->attach($user->id);

    $typeIds = NotificationType::query()->pluck('id')->take(2)->all();

    $this->withHeaders(apiHeaders($user))
        ->putJson('/api/notification-settings', [
            'notification_type_ids' => $typeIds,
            'account_ids' => [$account->id],
        ])
        ->assertOk();

    expect($user->notificationTypes()->pluck('notification_types.id')->all())->toBe($typeIds);
    expect($user->notificableAccounts()->pluck('accounts.id')->all())->toBe([$account->id]);
});

it('creates and updates accounts and transactions through the api', function () {
    seedNotificationTypes();
    $user = User::factory()->create();

    $accountResponse = $this->withHeaders(apiHeaders($user))
        ->postJson('/api/accounts', [
            'name' => 'Main Account',
            'color' => '#00ffaa',
            'description' => 'Primary account',
            'virtual' => false,
            'credit_card' => false,
            'feed_account_id' => null,
        ])
        ->assertCreated();

    $accountId = $accountResponse->json('data.id');

    $this->withHeaders(apiHeaders($user))
        ->postJson('/api/transactions', [
            'type' => 'outcome',
            'status' => 'completed',
            'concept' => 'Groceries',
            'amount' => 250,
            'account_id' => $accountId,
            'split_between_users' => false,
            'scheduled_at' => now()->toDateString(),
            'financial_goal_id' => null,
        ])
        ->assertCreated()
        ->assertJsonPath('data.concept', 'Groceries');

    expect(Account::findOrFail($accountId)->fresh()->balance)->toBe(-250.0);

    $transaction = Transaction::query()->where('concept', 'Groceries')->firstOrFail();

    $this->withHeaders(apiHeaders($user))
        ->putJson("/api/transactions/{$transaction->id}", [
            'type' => 'outcome',
            'status' => 'completed',
            'concept' => 'Groceries and fuel',
            'amount' => 300,
            'account_id' => $accountId,
            'split_between_users' => false,
            'scheduled_at' => now()->toDateString(),
            'financial_goal_id' => null,
        ])
        ->assertOk()
        ->assertJsonPath('data.concept', 'Groceries and fuel');

    expect(Account::findOrFail($accountId)->fresh()->balance)->toBe(-300.0);
});

it('filters and paginates index endpoints through query criteria', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create([
        'user_id' => $user->id,
        'virtual' => false,
        'credit_card' => false,
    ]);
    $account->users()->attach($user->id);

    Transaction::factory()->count(22)->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'type' => 'income',
        'status' => 'completed',
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'type' => 'outcome',
        'status' => 'pending',
    ]);

    $this->withHeaders(apiHeaders($user))
        ->getJson("/api/transactions?type=income&status=completed&account_id={$account->id}&per_page=5")
        ->assertOk()
        ->assertJsonPath('meta.per_page', 5)
        ->assertJsonPath('meta.total', 22)
        ->assertJsonCount(5, 'data');

    $this->withHeaders(apiHeaders($user))
        ->getJson('/api/accounts?credit_card=0&virtual=0')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 20)
        ->assertJsonPath('meta.total', 1);
});

it('creates account invites and lets the invited user accept them', function () {
    Notification::fake();
    seedNotificationTypes();

    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);
    $account->users()->attach($owner->id);
    $owner->notificationTypes()->sync(NotificationType::query()->pluck('id')->all());
    $owner->notificableAccounts()->sync([$account->id]);
    $invitee->notificationTypes()->sync(NotificationType::query()->pluck('id')->all());

    $response = $this->withHeaders(apiHeaders($owner))
        ->postJson('/api/account-invites', [
            'account_id' => $account->id,
            'email' => $invitee->email,
            'percentage' => 50,
        ])
        ->assertCreated();

    $inviteId = $response->json('data.id');

    Notification::assertSentOnDemand(InviteAccountEmail::class);

    $this->withHeaders(apiHeaders($invitee))
        ->putJson("/api/account-invites/{$inviteId}", [
            'status' => 'accepted',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted');

    expect($account->fresh()->users()->pluck('users.id')->all())->toContain($invitee->id);
});

it('manages nested account users, invites, transactions and financial goals', function () {
    Notification::fake();
    seedNotificationTypes();

    $owner = User::factory()->create();
    $member = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);
    $account->users()->attach($owner->id, ['percentage' => 100]);

    $userResponse = $this->withHeaders(apiHeaders($owner))
        ->postJson("/api/accounts/{$account->id}/users", [
            'user_id' => $member->id,
            'percentage' => 40,
        ])
        ->assertCreated();

    expect($account->fresh()->users()->where('users.id', $member->id)->first()?->pivot?->percentage)->toBe(40);

    $this->withHeaders(apiHeaders($owner))
        ->putJson("/api/accounts/{$account->id}/users/{$member->id}", [
            'percentage' => 55,
        ])
        ->assertOk();

    $this->withHeaders(apiHeaders($owner))
        ->getJson("/api/accounts/{$account->id}/users?percentage=55")
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    $goalResponse = $this->withHeaders(apiHeaders($owner))
        ->postJson("/api/accounts/{$account->id}/financial-goals", [
            'name' => 'Emergency Fund',
            'amount' => 5000,
            'must_completed_at' => now()->addMonth()->toDateString(),
        ])
        ->assertCreated();

    $goalId = $goalResponse->json('data.id');

    $transactionResponse = $this->withHeaders(apiHeaders($owner))
        ->postJson("/api/accounts/{$account->id}/transactions", [
            'type' => 'income',
            'status' => 'completed',
            'concept' => 'Salary',
            'amount' => 5000,
            'split_between_users' => false,
            'scheduled_at' => now()->toDateString(),
            'financial_goal_id' => $goalId,
        ])
        ->assertCreated();

    $transactionId = $transactionResponse->json('data.id');

    $this->withHeaders(apiHeaders($owner))
        ->getJson("/api/accounts/{$account->id}/transactions?type=income&financial_goal_id={$goalId}")
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    $inviteResponse = $this->withHeaders(apiHeaders($owner))
        ->postJson("/api/accounts/{$account->id}/invites", [
            'email' => 'invitee@example.com',
            'percentage' => 20,
        ])
        ->assertCreated();

    $inviteId = $inviteResponse->json('data.id');

    $this->withHeaders(apiHeaders($owner))
        ->getJson("/api/accounts/{$account->id}/invites?status=pending")
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    $this->withHeaders(apiHeaders($owner))
        ->deleteJson("/api/accounts/{$account->id}/invites/{$inviteId}")
        ->assertOk();

    $this->withHeaders(apiHeaders($owner))
        ->deleteJson("/api/accounts/{$account->id}/transactions/{$transactionId}")
        ->assertOk();

    $this->withHeaders(apiHeaders($owner))
        ->deleteJson("/api/accounts/{$account->id}/financial-goals/{$goalId}")
        ->assertOk();
});

it('creates subscription payments and registers a transaction when paid', function () {
    seedNotificationTypes();
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);
    $account->users()->attach($user->id);
    $this->actingAs($user);

    $subscription = Subscription::factory()->create([
        'user_id' => $user->id,
        'feed_account_id' => $account->id,
    ]);

    $response = $this->withHeaders(apiHeaders($user))
        ->postJson('/api/subscription-payments', [
            'subscription_id' => $subscription->id,
            'scheduled_at' => now()->toDateString(),
            'amount' => 199.99,
            'status' => 'paid',
            'account_id' => $account->id,
        ])
        ->assertCreated();

    $paymentId = $response->json('data.id');

    expect(SubscriptionPayment::findOrFail($paymentId)->status->value)->toBe('paid');
    expect(Transaction::query()->where('account_id', $account->id)->count())->toBe(1);
});

it('manages fixed incomes, partials and outcomes', function () {
    $user = User::factory()->create();

    $fixedIncomeId = $this->withHeaders(apiHeaders($user))
        ->postJson('/api/fixed-incomes', [
            'name' => 'Salary',
            'frequency' => 'monthly',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withHeaders(apiHeaders($user))
        ->postJson('/api/partial-fixed-incomes', [
            'fixed_income_id' => $fixedIncomeId,
            'name' => 'Bonus',
            'amount' => 1000,
        ])
        ->assertCreated();

    $this->withHeaders(apiHeaders($user))
        ->postJson('/api/fixed-outcomes', [
            'fixed_income_id' => $fixedIncomeId,
            'name' => 'Savings',
            'amount' => 200,
            'type' => 'savings',
        ])
        ->assertCreated();

    expect(FixedIncome::query()->count())->toBe(1);
    expect(PartialFixedIncome::query()->count())->toBe(1);
    expect(FixedOutcome::query()->count())->toBe(1);
});

it('generates telegram verification codes with rate limits', function () {
    $user = User::factory()->create(['telegram_chat_id' => null]);

    $headers = apiHeaders($user);

    $this->withHeaders($headers)->postJson('/api/telegram-verification-codes', [])
        ->assertCreated();
    $this->withHeaders($headers)->postJson('/api/telegram-verification-codes', [])
        ->assertCreated();
    $this->withHeaders($headers)->postJson('/api/telegram-verification-codes', [])
        ->assertCreated();
    $this->withHeaders($headers)->postJson('/api/telegram-verification-codes', [])
        ->assertStatus(422);

    expect(TelegramVerificationCode::query()->where('user_id', $user->id)->count())->toBe(3);
});
