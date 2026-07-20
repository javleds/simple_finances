<?php

use App\Enums\Frequency;
use App\Enums\InviteStatus;
use App\Models\Account;
use App\Models\AccountInvite;
use App\Models\FixedIncome;
use App\Models\FixedOutcome;
use App\Models\FinancialGoal;
use App\Models\NotificationType;
use App\Models\PartialFixedIncome;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\TelegramVerificationCode;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\InviteAccountApiEmail;
use App\Notifications\InviteAccountEmail;
use App\Services\Auth\JwtTokenService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\DB;
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

it('rate limits register requests by email and ip', function () {
    Notification::fake();
    seedNotificationTypes();

    $payload = [
        'name' => 'Rate Limited User',
        'email' => 'register-limit@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'phone_number' => '555-1000',
        'terms_accepted' => true,
        'privacy_policy_accepted' => true,
    ];

    $this->postJson('/api/auth/register', $payload)
        ->assertCreated();

    $this->postJson('/api/auth/register', $payload)
        ->assertStatus(422);

    $this->postJson('/api/auth/register', $payload)
        ->assertStatus(422);

    $this->postJson('/api/auth/register', $payload)
        ->assertStatus(429);
});

it('returns the pending invitations redirect after registering from an invitation action', function () {
    config()->set('app.spa_url', 'https://spa.example.test');
    Notification::fake();
    seedNotificationTypes();

    AccountInvite::factory()->create([
        'email' => 'invited-register@example.com',
        'status' => InviteStatus::Pending,
    ]);

    $this->postJson('/api/auth/register', [
        'name' => 'Invited User',
        'email' => 'invited-register@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'phone_number' => null,
        'terms_accepted' => true,
        'privacy_policy_accepted' => true,
        'post_auth_action' => 'account-invites',
    ])
        ->assertCreated()
        ->assertJsonPath('meta.post_auth_redirect.action', 'account-invites')
        ->assertJsonPath('meta.post_auth_redirect.url', 'https://spa.example.test/admin/invitations');
});

it('returns the pending invitations redirect after logging in from an invitation action', function () {
    config()->set('app.spa_url', 'https://spa.example.test');
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'invited-login@example.com',
    ]);

    AccountInvite::factory()->create([
        'email' => $user->email,
        'status' => InviteStatus::Pending,
    ]);

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'post_auth_action' => 'account-invites',
    ])
        ->assertOk()
        ->assertJsonPath('meta.post_auth_redirect.action', 'account-invites')
        ->assertJsonPath('meta.post_auth_redirect.url', 'https://spa.example.test/admin/invitations');
});

it('rate limits password recovery requests by email and ip', function () {
    $payload = [
        'email' => 'password-recovery-limit@example.com',
    ];

    for ($attempt = 0; $attempt < 3; $attempt++) {
        $this->postJson('/api/auth/password-recovery', $payload)
            ->assertStatus(422);
    }

    $this->postJson('/api/auth/password-recovery', $payload)
        ->assertStatus(429);
});

it('builds password recovery links with the configured spa url', function () {
    config()->set('app.spa_url', 'https://spa.example.test');

    $user = User::factory()->create([
        'email' => 'password-reset@example.com',
    ]);

    $notification = new ResetPassword('reset-token');
    $mailMessage = $notification->toMail($user);

    expect($mailMessage->actionUrl)->toBe('https://spa.example.test/password-reset/reset?token=reset-token&email=password-reset%40example.com');
});

it('builds email verification links that verify through the api and redirect to the spa', function () {
    config()->set('app.spa_url', 'https://spa.example.test');

    $user = User::factory()->unverified()->create([
        'email' => 'verification-link@example.com',
    ]);

    $notification = new VerifyEmail;
    $mailMessage = $notification->toMail($user);
    $verificationUrl = $mailMessage->actionUrl;

    expect($verificationUrl)->toContain('/api/auth/email-verification/'.$user->id.'/')
        ->and($verificationUrl)->toContain('redirect_to_spa=1');

    $this->get($verificationUrl)
        ->assertRedirect('https://spa.example.test/email-verification?status=verified');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('redirects verified invited users to their pending invitations', function () {
    config()->set('app.spa_url', 'https://spa.example.test');

    $user = User::factory()->unverified()->create([
        'email' => 'verification-invited@example.com',
    ]);

    AccountInvite::factory()->create([
        'email' => $user->email,
        'status' => InviteStatus::Pending,
    ]);

    $notification = new VerifyEmail;
    $mailMessage = $notification->toMail($user);

    $this->get($mailMessage->actionUrl)
        ->assertRedirect('https://spa.example.test/admin/invitations');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
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

    expect((float) Account::withoutGlobalScopes()->findOrFail($accountId)->users()->findOrFail($user->id)->pivot->percentage)->toBe(100.0);

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
        ->assertJsonPath('data.concept', 'Groceries')
        ->assertJsonPath('meta.account.id', $accountId)
        ->assertJsonPath('meta.account.balance', -250.0);

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
        ->assertJsonPath('data.concept', 'Groceries and fuel')
        ->assertJsonPath('meta.account.id', $accountId)
        ->assertJsonPath('meta.account.balance', -300.0);

    $this->withHeaders(apiHeaders($user))
        ->deleteJson("/api/transactions/{$transaction->id}")
        ->assertOk()
        ->assertJsonPath('meta.account.id', $accountId)
        ->assertJsonPath('meta.account.balance', 0.0)
        ->assertJsonPath('meta.subtransactions', []);

    expect(Account::findOrFail($accountId)->fresh()->balance)->toBe(0.0);
});

it('rejects pending transaction writes', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);
    $account->users()->attach($user->id);

    $this->withHeaders(apiHeaders($user))
        ->postJson("/api/accounts/{$account->id}/transactions", [
            'type' => 'outcome',
            'status' => 'pending',
            'concept' => 'Unsupported pending expense',
            'amount' => 100,
            'split_between_users' => false,
            'scheduled_at' => now()->toDateString(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
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

    $this->withHeaders(apiHeaders($user))
        ->getJson("/api/transactions?account_id={$account->id}&per_page=500")
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100);
});

it('supports array query values in index endpoint filters', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);
    $account->users()->attach($user->id);

    Transaction::factory()->income()->completed()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
    ]);
    Transaction::factory()->outcome()->completed()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
    ]);
    Transaction::factory()->income()->pending()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
    ]);

    $this->withHeaders(apiHeaders($user))
        ->getJson('/api/transactions?type[]=income&type[]=outcome&status[]=completed')
        ->assertOk()
        ->assertJsonPath('meta.total', 2);
});

it('searches visible accounts by name', function () {
    $user = User::factory()->create();

    $savingsAccount = Account::factory()->create([
        'user_id' => $user->id,
        'name' => 'Emergency Savings',
    ]);
    $checkingAccount = Account::factory()->create([
        'user_id' => $user->id,
        'name' => 'Daily Checking',
    ]);

    $savingsAccount->users()->attach($user->id);
    $checkingAccount->users()->attach($user->id);

    $response = $this->withHeaders(apiHeaders($user))
        ->getJson('/api/accounts?search=savings')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    expect(collect($response->json('data'))->pluck('name')->all())->toBe(['Emergency Savings']);
});

it('filters soft deleted accounts by deleted at state', function () {
    $user = User::factory()->create();

    $activeAccount = Account::factory()->create([
        'user_id' => $user->id,
        'name' => 'Active account',
    ]);
    $deletedAccount = Account::factory()->create([
        'user_id' => $user->id,
        'name' => 'Deleted account',
    ]);

    $activeAccount->users()->attach($user->id);
    $deletedAccount->users()->attach($user->id);
    $deletedAccount->delete();

    $this->withHeaders(apiHeaders($user))
        ->getJson('/api/accounts')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'Active account');

    $this->withHeaders(apiHeaders($user))
        ->getJson('/api/accounts?deleted_at=0')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'Active account');

    $this->withHeaders(apiHeaders($user))
        ->getJson('/api/accounts?deleted_at=1')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'Deleted account');
});

it('returns member summaries for shared accounts', function () {
    $owner = User::factory()->create(['name' => 'Account Owner']);
    $sharedUser = User::factory()->create(['name' => 'Shared User']);
    $account = Account::factory()->create(['user_id' => $owner->id]);
    $account->users()->attach([
        $owner->id => ['percentage' => 0],
        $sharedUser->id => ['percentage' => 50],
    ]);

    Transaction::factory()->income()->completed()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
        'amount' => 300,
        'custodian_user_id' => $owner->id,
    ]);

    $this->withHeaders(apiHeaders($owner))
        ->getJson("/api/accounts/{$account->id}")
        ->assertOk()
        ->assertJsonPath('data.custody_by_user.0.user_id', $owner->id)
        ->assertJsonPath('data.custody_by_user.0.user_name', 'Account Owner')
        ->assertJsonPath('data.custody_by_user.1.user_id', $sharedUser->id)
        ->assertJsonPath('data.custody_by_user.1.user_name', 'Shared User')
        ->assertJsonCount(2, 'data.custody_by_user')
        ->assertJsonCount(2, 'data.settlements_by_user')
        ->assertJsonPath('data.pending_reimbursements', []);
});

it('lists shared account reimbursements and settles them through the api', function () {
    $owner = User::factory()->create(['name' => 'Account Owner']);
    $sharedUser = User::factory()->create(['name' => 'Shared User']);
    $account = Account::factory()->create(['user_id' => $owner->id]);
    $account->users()->sync([
        $owner->id => ['percentage' => 50],
        $sharedUser->id => ['percentage' => 50],
    ]);

    $this->actingAs($sharedUser);
    $transaction = app(\App\Services\Transaction\TransactionCreator::class)->execute(\App\Dto\TransactionFormDto::fromFormArray([
        'type' => \App\Enums\TransactionType::Outcome,
        'status' => \App\Enums\TransactionStatus::Completed,
        'concept' => 'Shared dinner',
        'amount' => 1000,
        'account_id' => $account->id,
        'paid_by_user_id' => $sharedUser->id,
        'payment_source' => \App\Enums\TransactionPaymentSource::MemberOutOfPocket,
        'split_between_users' => true,
        'user_payments' => [
            ['user_id' => $owner->id, 'percentage' => 50],
            ['user_id' => $sharedUser->id, 'percentage' => 50],
        ],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]));

    $this->withHeaders(apiHeaders($owner))
        ->getJson('/api/accounts?per_page=100')
        ->assertOk()
        ->assertJsonPath('data.0.pending_reimbursements.0.from_user_id', $owner->id)
        ->assertJsonPath('data.0.pending_reimbursements.0.to_user_id', $sharedUser->id)
        ->assertJsonPath('data.0.pending_reimbursements.0.amount', 500.0)
        ->assertJsonPath('data.0.pending_reimbursements.0.items.0.transaction_id', (string) $transaction->id)
        ->assertJsonPath('data.0.pending_reimbursements.0.items.0.concept', 'Shared dinner')
        ->assertJsonPath('data.0.pending_reimbursements.0.items.0.amount', 500.0);

    $this->withHeaders(apiHeaders($owner))
        ->postJson("/api/accounts/{$account->id}/member-transfers", [
            'from_user_id' => $owner->id,
            'to_user_id' => $sharedUser->id,
            'amount' => 500,
            'description' => 'Dinner reimbursement',
        ])
        ->assertCreated()
        ->assertJsonPath('meta.pending_reimbursements', [])
        ->assertJsonPath('meta.settlements_by_user.0.amount', 0.0)
        ->assertJsonPath('meta.settlements_by_user.1.amount', 0.0);
});

it('returns member summaries for accounts without shared users', function () {
    $owner = User::factory()->create(['name' => 'Single Owner']);
    $account = Account::factory()->create(['user_id' => $owner->id]);
    $account->users()->attach($owner->id);

    $this->withHeaders(apiHeaders($owner))
        ->getJson("/api/accounts/{$account->id}")
        ->assertOk()
        ->assertJsonPath('data.custody_by_user.0.user_id', $owner->id)
        ->assertJsonPath('data.custody_by_user.0.user_name', 'Single Owner')
        ->assertJsonPath('data.custody_by_user.0.amount', 0.0)
        ->assertJsonCount(1, 'data.custody_by_user')
        ->assertJsonPath('data.pending_reimbursements', []);
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

    Notification::assertSentOnDemand(InviteAccountApiEmail::class);

    $this->withHeaders(apiHeaders($invitee))
        ->putJson("/api/account-invites/{$inviteId}", [
            'status' => 'accepted',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted');

    expect($account->fresh()->users()->pluck('users.id')->all())->toContain($invitee->id)
        ->and(DB::table('account_user')
            ->where('account_id', $account->id)
            ->where('user_id', $invitee->id)
            ->count())->toBe(1);
});

it('lists only account invites addressed to the authenticated user', function () {
    $user = User::factory()->create();
    $sender = User::factory()->create();
    $otherUser = User::factory()->create();
    $account = Account::factory()->create([
        'name' => 'Shared Budget',
        'user_id' => $sender->id,
    ]);
    $account->users()->attach($sender->id);

    $receivedInvite = AccountInvite::factory()->create([
        'account_id' => $account->id,
        'email' => $user->email,
        'user_id' => $sender->id,
    ]);

    AccountInvite::factory()->create([
        'email' => $otherUser->email,
        'user_id' => $user->id,
    ]);

    AccountInvite::factory()->create([
        'email' => $otherUser->email,
        'user_id' => $sender->id,
    ]);

    $response = $this->withHeaders(apiHeaders($user))
        ->getJson('/api/account-invites')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.account.name', 'Shared Budget');

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$receivedInvite->id]);
});

it('sends the invitation email before persisting account invites from the nested endpoint', function () {
    Notification::fake();
    seedNotificationTypes();

    $owner = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);
    $account->users()->attach($owner->id);
    $owner->notificationTypes()->sync(NotificationType::query()->pluck('id')->all());

    $this->withHeaders(apiHeaders($owner))
        ->postJson("/api/accounts/{$account->id}/invites", [
            'email' => 'external@example.com',
            'percentage' => 20,
        ])
        ->assertCreated();

    expect(AccountInvite::query()->where('account_id', $account->id)->count())->toBe(1);

    Notification::assertSentOnDemand(InviteAccountApiEmail::class);
});

it('filters nested account invites by multiple statuses', function () {
    $owner = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);
    $account->users()->attach($owner->id);

    AccountInvite::factory()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
        'status' => InviteStatus::Pending,
    ]);

    AccountInvite::factory()->accepted()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
    ]);

    AccountInvite::factory()->declined()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
    ]);

    $response = $this->withHeaders(apiHeaders($owner))
        ->getJson("/api/accounts/{$account->id}/invites?status=pending,accepted")
        ->assertOk()
        ->assertJsonPath('meta.total', 2);

    expect(collect($response->json('data'))->pluck('status')->sort()->values()->all())
        ->toBe(['accepted', 'pending']);
});

it('searches nested account invites by email', function () {
    $owner = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);
    $account->users()->attach($owner->id);

    $matchingInvite = AccountInvite::factory()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
        'email' => 'person@example.com',
    ]);

    AccountInvite::factory()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
        'email' => 'another@example.com',
    ]);

    $response = $this->withHeaders(apiHeaders($owner))
        ->getJson("/api/accounts/{$account->id}/invites?search=person")
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$matchingInvite->id]);
});

it('searches nested account transactions by concept', function () {
    $owner = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);
    $account->users()->attach($owner->id);

    $matchingTransaction = Transaction::factory()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
        'concept' => 'Grocery store',
    ]);

    Transaction::factory()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
        'concept' => 'Internet bill',
    ]);

    $response = $this->withHeaders(apiHeaders($owner))
        ->getJson("/api/accounts/{$account->id}/transactions?search=grocery")
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$matchingTransaction->id]);
});

it('searches nested account financial goals by name', function () {
    $owner = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);
    $account->users()->attach($owner->id);

    $matchingGoal = FinancialGoal::factory()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
        'name' => 'Vacation fund',
    ]);

    FinancialGoal::factory()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
        'name' => 'Emergency fund',
    ]);

    $response = $this->withHeaders(apiHeaders($owner))
        ->getJson("/api/accounts/{$account->id}/financial-goals?search=vacation")
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$matchingGoal->id]);
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

    $users = $account->fresh()->users()->get();

    expect((float) $users->firstWhere('id', $member->id)->pivot->percentage)->toBe(40.0)
        ->and((float) $users->firstWhere('id', $owner->id)->pivot->percentage)->toBe(60.0)
        ->and(round($users->sum(fn (User $user): float => (float) $user->pivot->percentage), 2))->toBe(100.0);

    $this->withHeaders(apiHeaders($owner))
        ->putJson("/api/accounts/{$account->id}/users/{$member->id}", [
            'percentage' => 55,
        ])
        ->assertOk();

    $users = $account->fresh()->users()->get();

    expect((float) $users->firstWhere('id', $owner->id)->pivot->percentage)->toBe(45.0)
        ->and((float) $users->firstWhere('id', $member->id)->pivot->percentage)->toBe(55.0)
        ->and(round($users->sum(fn (User $user): float => (float) $user->pivot->percentage), 2))->toBe(100.0);

    $this->withHeaders(apiHeaders($owner))
        ->getJson("/api/accounts/{$account->id}/users?percentage=55")
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    $this->withHeaders(apiHeaders($owner))
        ->deleteJson("/api/accounts/{$account->id}/users/{$member->id}")
        ->assertOk();

    $users = $account->fresh()->users()->get();

    expect($users)->toHaveCount(1)
        ->and($users->first()->id)->toBe($owner->id)
        ->and((float) $users->first()->pivot->percentage)->toBe(100.0);

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
        ->assertCreated()
        ->assertJsonPath('data.financial_goal_id', $goalId)
        ->assertJsonPath('meta.account.id', $account->id)
        ->assertJsonPath('meta.account.balance', 5000.0);

    $transactionId = $transactionResponse->json('data.id');

    $this->withHeaders(apiHeaders($owner))
        ->getJson("/api/accounts/{$account->id}/transactions?type=income&financial_goal_id={$goalId}")
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    $goalsResponse = $this->withHeaders(apiHeaders($owner))
        ->getJson("/api/accounts/{$account->id}/financial-goals")
        ->assertOk()
        ->assertJsonPath('data.0.achieved_amount', 5000.0)
        ->assertJsonPath('data.0.progress', 100.0);

    expect($goalsResponse->json('data.0.amount'))->toBeFloat()
        ->and($goalsResponse->json('data.0.achieved_amount'))->toBeFloat()
        ->and($goalsResponse->json('data.0.progress'))->toBeFloat();

    $this->withHeaders(apiHeaders($owner))
        ->putJson("/api/accounts/{$account->id}/transactions/{$transactionId}", [
            'type' => 'income',
            'status' => 'completed',
            'concept' => 'Salary Updated',
            'amount' => 4500,
            'split_between_users' => false,
            'scheduled_at' => now()->toDateString(),
            'financial_goal_id' => $goalId,
        ])
        ->assertOk()
        ->assertJsonPath('data.financial_goal_id', $goalId);

    $this->withHeaders(apiHeaders($owner))
        ->getJson("/api/accounts/{$account->id}/financial-goals")
        ->assertOk()
        ->assertJsonPath('data.0.achieved_amount', 4500.0)
        ->assertJsonPath('data.0.progress', 90.0);

    $this->withHeaders(apiHeaders($owner))
        ->putJson("/api/accounts/{$account->id}/financial-goals/{$goalId}", [
            'name' => 'Emergency Fund',
            'amount' => 9000,
            'must_completed_at' => now()->addMonth()->toDateString(),
        ])
        ->assertOk()
        ->assertJsonPath('data.amount', 9000.0)
        ->assertJsonPath('data.achieved_amount', 4500.0)
        ->assertJsonPath('data.progress', 50.0);

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
        ->assertOk()
        ->assertJsonPath('meta.account.id', $account->id)
        ->assertJsonPath('meta.account.balance', 0.0);

    $this->withHeaders(apiHeaders($owner))
        ->getJson("/api/accounts/{$account->id}/financial-goals")
        ->assertOk()
        ->assertJsonPath('data.0.achieved_amount', 0.0)
        ->assertJsonPath('data.0.progress', 0.0);

    $this->withHeaders(apiHeaders($owner))
        ->deleteJson("/api/accounts/{$account->id}/financial-goals/{$goalId}")
        ->assertOk();
});

it('bulk updates account user percentages with an exact normalized total', function () {
    $owner = User::factory()->create();
    $memberOne = User::factory()->create();
    $memberTwo = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);

    $account->users()->sync([
        $owner->id => ['percentage' => 100],
        $memberOne->id => ['percentage' => 0],
        $memberTwo->id => ['percentage' => 0],
    ]);

    $response = $this->withHeaders(apiHeaders($owner))
        ->putJson("/api/accounts/{$account->id}/users", [
            'users' => [
                ['user_id' => $owner->id, 'percentage' => 33.334],
                ['user_id' => $memberOne->id, 'percentage' => 33.331],
                ['user_id' => $memberTwo->id, 'percentage' => 33.335],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('meta.account_id', $account->id)
        ->assertJsonCount(3, 'data');

    expect($response->json('data'))->toBe([
        [
            'id' => $owner->id,
            'user_id' => $owner->id,
            'name' => $owner->name,
            'email' => $owner->email,
            'percentage' => '33.33',
        ],
        [
            'id' => $memberOne->id,
            'user_id' => $memberOne->id,
            'name' => $memberOne->name,
            'email' => $memberOne->email,
            'percentage' => '33.33',
        ],
        [
            'id' => $memberTwo->id,
            'user_id' => $memberTwo->id,
            'name' => $memberTwo->name,
            'email' => $memberTwo->email,
            'percentage' => '33.34',
        ],
    ])
        ->and((float) $response->json('meta.total_percentage'))->toBe(100.0)
        ->and((float) $account->fresh()->users()->findOrFail($owner->id)->pivot->percentage)->toBe(33.33)
        ->and((float) $account->fresh()->users()->findOrFail($memberOne->id)->pivot->percentage)->toBe(33.33)
        ->and((float) $account->fresh()->users()->findOrFail($memberTwo->id)->pivot->percentage)->toBe(33.34);
});

it('rejects bulk account user percentage updates when the total is not exactly one hundred', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);

    $account->users()->sync([
        $owner->id => ['percentage' => 50],
        $member->id => ['percentage' => 50],
    ]);

    $this->withHeaders(apiHeaders($owner))
        ->putJson("/api/accounts/{$account->id}/users", [
            'users' => [
                ['user_id' => $owner->id, 'percentage' => 60],
                ['user_id' => $member->id, 'percentage' => 39.99],
            ],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['users']);
});

it('rejects bulk account user percentage updates when the request users do not match the account users', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $outsider = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);

    $account->users()->sync([
        $owner->id => ['percentage' => 50],
        $member->id => ['percentage' => 50],
    ]);

    $this->withHeaders(apiHeaders($owner))
        ->putJson("/api/accounts/{$account->id}/users", [
            'users' => [
                ['user_id' => $owner->id, 'percentage' => 50],
                ['user_id' => $outsider->id, 'percentage' => 50],
            ],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['users']);
});

it('returns the created transaction with allocations when storing a split account transaction', function () {
    $owner = User::factory()->create();
    $memberOne = User::factory()->create();
    $memberTwo = User::factory()->create();
    $memberThree = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);

    $account->users()->attach($owner->id, ['percentage' => 100]);
    $account->users()->attach($memberOne->id, ['percentage' => 33.0]);
    $account->users()->attach($memberTwo->id, ['percentage' => 33.33]);
    $account->users()->attach($memberThree->id, ['percentage' => 33.67]);

    $response = $this->withHeaders(apiHeaders($owner))
        ->postJson("/api/accounts/{$account->id}/transactions", [
            'type' => 'outcome',
            'status' => 'completed',
            'concept' => 'Shared dinner',
            'amount' => 300,
            'split_between_users' => true,
            'user_payments' => [
                [
                    'user_id' => $memberOne->id,
                    'percentage' => 33.0,
                ],
                [
                    'user_id' => $memberTwo->id,
                    'percentage' => 33.33,
                ],
                [
                    'user_id' => $memberThree->id,
                    'percentage' => 33.67,
                ],
            ],
            'scheduled_at' => now()->toDateString(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'outcome')
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.payment_source', 'account_fund')
        ->assertJsonPath('data.paid_by_user_id', $owner->id)
        ->assertJsonCount(3, 'data.allocations')
        ->assertJsonPath('meta.account.id', $account->id)
        ->assertJsonPath('meta.settlements_by_user.0.user_id', $owner->id)
        ->assertJsonPath('meta.settlements_by_user.0.amount', 0.0)
        ->assertJsonPath('meta.pending_reimbursements', []);

    $transaction = $response->json('data');
    $allocations = collect($transaction['allocations']);

    expect(Transaction::query()->whereKey($transaction['id'])->exists())->toBeTrue()
        ->and(Transaction::query()->where('parent_transaction_id', $transaction['id'])->count())->toBe(0)
        ->and((float) $transaction['amount'])->toBe(300.0)
        ->and($allocations->pluck('user_id')->all())->toBe([$memberOne->id, $memberTwo->id, $memberThree->id])
        ->and($allocations->pluck('percentage')->all())->toBe([33.0, 33.33, 33.67])
        ->and($allocations->pluck('amount')->all())->toBe([99.0, 99.99, 101.01])
        ->and((float) $response->json('meta.account.balance'))->toBe(-300.0);

    $this->withHeaders(apiHeaders($owner))
        ->deleteJson("/api/accounts/{$account->id}/transactions/{$transaction['id']}")
        ->assertOk()
        ->assertJsonPath('meta.account.id', $account->id)
        ->assertJsonPath('meta.subtransactions', [])
        ->assertJsonPath('meta.pending_reimbursements', []);
});

it('lists split transactions as single movements with allocations', function () {
    $owner = User::factory()->create();
    $memberOne = User::factory()->create();
    $memberTwo = User::factory()->create();
    $memberThree = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);

    $account->users()->attach($owner->id, ['percentage' => 100]);
    $account->users()->attach($memberOne->id, ['percentage' => 33.33]);
    $account->users()->attach($memberTwo->id, ['percentage' => 33.33]);
    $account->users()->attach($memberThree->id, ['percentage' => 33.34]);

    $this->withHeaders(apiHeaders($owner))
        ->postJson("/api/accounts/{$account->id}/transactions", [
            'type' => 'outcome',
            'status' => 'completed',
            'concept' => 'Split order',
            'amount' => 100,
            'split_between_users' => true,
            'user_payments' => [
                [
                    'user_id' => $memberOne->id,
                    'percentage' => 33.33,
                ],
                [
                    'user_id' => $memberTwo->id,
                    'percentage' => 33.33,
                ],
                [
                    'user_id' => $memberThree->id,
                    'percentage' => 33.34,
                ],
            ],
            'scheduled_at' => now()->toDateString(),
        ])
        ->assertCreated();

    $response = $this->withHeaders(apiHeaders($owner))
        ->getJson("/api/accounts/{$account->id}/transactions")
        ->assertOk();

    $transactions = collect($response->json('data'));

    expect($transactions)->toHaveCount(1)
        ->and($transactions->first()['type'])->toBe('outcome')
        ->and($transactions->first()['status'])->toBe('completed')
        ->and($transactions->first()['concept'])->toBe('Split order')
        ->and($transactions->first()['allocations'])->toHaveCount(3);
});

it('marks account transactions that the current user still needs to reimburse', function () {
    $owner = User::factory()->create();
    $sharedUser = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);
    $account->users()->sync([
        $owner->id => ['percentage' => 50],
        $sharedUser->id => ['percentage' => 50],
    ]);

    $this->actingAs($sharedUser);
    app(\App\Services\Transaction\TransactionCreator::class)->execute(\App\Dto\TransactionFormDto::fromFormArray([
        'type' => \App\Enums\TransactionType::Outcome,
        'status' => \App\Enums\TransactionStatus::Completed,
        'concept' => 'Shared supplies',
        'amount' => 800,
        'account_id' => $account->id,
        'paid_by_user_id' => $sharedUser->id,
        'payment_source' => \App\Enums\TransactionPaymentSource::MemberOutOfPocket,
        'split_between_users' => true,
        'user_payments' => [
            ['user_id' => $owner->id, 'percentage' => 50],
            ['user_id' => $sharedUser->id, 'percentage' => 50],
        ],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]));

    $this->actingAs($owner)
        ->withHeaders(apiHeaders($owner))
        ->getJson("/api/accounts/{$account->id}/transactions")
        ->assertOk()
        ->assertJsonPath('data.0.concept', 'Shared supplies')
        ->assertJsonPath('data.0.current_user_pending_reimbursement_amount', 400.0)
        ->assertJsonPath('data.0.current_user_receivable_reimbursement_amount', 0.0);

    $this->actingAs($sharedUser)
        ->withHeaders(apiHeaders($sharedUser))
        ->getJson("/api/accounts/{$account->id}/transactions")
        ->assertOk()
        ->assertJsonPath('data.0.current_user_pending_reimbursement_amount', 0.0)
        ->assertJsonPath('data.0.current_user_receivable_reimbursement_amount', 400.0);
});

it('updates split allocations when editing a shared transaction through the api', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);

    $account->users()->sync([
        $owner->id => ['percentage' => 50],
        $member->id => ['percentage' => 50],
    ]);

    $createResponse = $this->withHeaders(apiHeaders($owner))
        ->postJson("/api/accounts/{$account->id}/transactions", [
            'type' => 'outcome',
            'status' => 'completed',
            'concept' => 'Original dinner',
            'amount' => 200,
            'split_between_users' => true,
            'user_payments' => [
                [
                    'user_id' => $owner->id,
                    'percentage' => 50,
                ],
                [
                    'user_id' => $member->id,
                    'percentage' => 50,
                ],
            ],
            'scheduled_at' => '2026-01-10',
        ])
        ->assertCreated();

    $transactionId = $createResponse->json('data.id');

    $updateResponse = $this->withHeaders(apiHeaders($owner))
        ->putJson("/api/accounts/{$account->id}/transactions/{$transactionId}", [
            'type' => 'outcome',
            'status' => 'completed',
            'concept' => 'Updated dinner',
            'amount' => 200,
            'split_between_users' => true,
            'user_payments' => [
                [
                    'user_id' => $owner->id,
                    'percentage' => 25,
                ],
                [
                    'user_id' => $member->id,
                    'percentage' => 75,
                ],
            ],
            'scheduled_at' => '2026-01-20',
        ])
        ->assertOk()
        ->assertJsonPath('data.concept', 'Updated dinner')
        ->assertJsonCount(2, 'data.allocations')
        ->assertJsonPath('meta.pending_reimbursements', []);

    expect(collect($updateResponse->json('data.allocations'))->pluck('amount')->all())->toBe([50.0, 150.0])
        ->and(collect($updateResponse->json('data.allocations'))->pluck('percentage')->all())->toBe([25.0, 75.0])
        ->and(str_starts_with($updateResponse->json('data.scheduled_at'), '2026-01-20'))->toBeTrue();
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

it('filters finished subscriptions from the API', function () {
    $user = User::factory()->create();

    Subscription::factory()->create([
        'user_id' => $user->id,
        'name' => 'Active subscription',
        'finished_at' => null,
        'next_payment_date' => '2026-01-10',
    ]);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'name' => 'Finished subscription',
        'finished_at' => '2026-01-01',
        'next_payment_date' => '2026-01-11',
    ]);

    $response = $this->withHeaders(apiHeaders($user))
        ->getJson('/api/subscriptions?finished=1')
        ->assertOk();

    expect(collect($response->json('data'))->pluck('name')->all())->toBe(['Finished subscription']);
});

it('filters active subscriptions from the API', function () {
    $user = User::factory()->create();

    Subscription::factory()->create([
        'user_id' => $user->id,
        'name' => 'Active subscription',
        'finished_at' => null,
        'next_payment_date' => '2026-01-10',
    ]);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'name' => 'Finished subscription',
        'finished_at' => '2026-01-01',
        'next_payment_date' => '2026-01-11',
    ]);

    $response = $this->withHeaders(apiHeaders($user))
        ->getJson('/api/subscriptions?finished=0')
        ->assertOk();

    expect(collect($response->json('data'))->pluck('name')->all())->toBe(['Active subscription']);
});

it('searches subscriptions by name', function () {
    $user = User::factory()->create();

    Subscription::factory()->create([
        'user_id' => $user->id,
        'name' => 'Cloud Storage',
        'next_payment_date' => '2026-01-10',
    ]);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'name' => 'Music Service',
        'next_payment_date' => '2026-01-11',
    ]);

    $response = $this->withHeaders(apiHeaders($user))
        ->getJson('/api/subscriptions?search=cloud')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    expect(collect($response->json('data'))->pluck('name')->all())->toBe(['Cloud Storage']);
});

it('filters subscriptions by frequency type', function () {
    $user = User::factory()->create();

    Subscription::factory()->create([
        'user_id' => $user->id,
        'name' => 'Monthly music',
        'frequency_type' => Frequency::Month,
        'next_payment_date' => '2026-01-10',
    ]);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'name' => 'Yearly cloud',
        'frequency_type' => Frequency::Year,
        'next_payment_date' => '2026-01-11',
    ]);

    $response = $this->withHeaders(apiHeaders($user))
        ->getJson('/api/subscriptions?frequency_type=years')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    expect(collect($response->json('data'))->pluck('name')->all())->toBe(['Yearly cloud']);
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

it('searches fixed incomes by name', function () {
    $user = User::factory()->create();

    FixedIncome::factory()->create([
        'user_id' => $user->id,
        'name' => 'Primary Payroll',
    ]);
    FixedIncome::factory()->create([
        'user_id' => $user->id,
        'name' => 'Consulting',
    ]);

    $response = $this->withHeaders(apiHeaders($user))
        ->getJson('/api/fixed-incomes?search=payroll')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    expect(collect($response->json('data'))->pluck('name')->all())->toBe(['Primary Payroll']);
});

it('searches fixed outcomes by name', function () {
    $user = User::factory()->create();
    $fixedIncome = FixedIncome::factory()->create(['user_id' => $user->id]);

    FixedOutcome::factory()->create([
        'user_id' => $user->id,
        'fixed_income_id' => $fixedIncome->id,
        'name' => 'Emergency Savings',
    ]);
    FixedOutcome::factory()->create([
        'user_id' => $user->id,
        'fixed_income_id' => $fixedIncome->id,
        'name' => 'Rent',
    ]);

    $response = $this->withHeaders(apiHeaders($user))
        ->getJson('/api/fixed-outcomes?search=emergency')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    expect(collect($response->json('data'))->pluck('name')->all())->toBe(['Emergency Savings']);
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
