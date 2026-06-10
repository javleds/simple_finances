<?php

use App\Enums\InviteStatus;
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
use App\Notifications\InviteAccountApiEmail;
use App\Notifications\InviteAccountEmail;
use App\Services\Auth\JwtTokenService;
use Illuminate\Auth\Notifications\ResetPassword;
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
        ->assertJsonPath('data.concept', 'Groceries')
        ->assertJsonPath('meta.account.id', $accountId)
        ->assertJsonPath('meta.account.balance', -250);

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
        ->assertJsonPath('meta.account.balance', -300);

    $this->withHeaders(apiHeaders($user))
        ->deleteJson("/api/transactions/{$transaction->id}")
        ->assertOk()
        ->assertJsonPath('meta.account.id', $accountId)
        ->assertJsonPath('meta.account.balance', 0);

    expect(Account::findOrFail($accountId)->fresh()->balance)->toBe(0.0);
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

    Notification::assertSentOnDemand(InviteAccountApiEmail::class);

    $this->withHeaders(apiHeaders($invitee))
        ->putJson("/api/account-invites/{$inviteId}", [
            'status' => 'accepted',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted');

    expect($account->fresh()->users()->pluck('users.id')->all())->toContain($invitee->id);
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
        ->assertCreated()
        ->assertJsonPath('meta.account.id', $account->id)
        ->assertJsonPath('meta.account.balance', 5000);

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
        ->assertOk()
        ->assertJsonPath('meta.account.id', $account->id)
        ->assertJsonPath('meta.account.balance', 0);

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

it('returns every created transaction when storing a split account transaction', function () {
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
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('data.0.type', 'outcome')
        ->assertJsonPath('data.0.status', 'completed')
        ->assertJsonPath('data.1.type', 'income')
        ->assertJsonPath('data.1.status', 'pending')
        ->assertJsonPath('meta.account.id', $account->id);

    $transactions = collect($response->json('data'));
    $transactionIds = $transactions->pluck('id')->all();

    expect($transactionIds)->toHaveCount(4)
        ->and(Transaction::query()->whereIn('id', $transactionIds)->count())->toBe(4)
        ->and(Transaction::query()->where('parent_transaction_id', $transactionIds[0])->count())->toBe(3)
        ->and((float) $transactions[0]['amount'])->toBe(300.0)
        ->and((float) $transactions[1]['percentage'])->toBe(33.0)
        ->and((float) $transactions[2]['percentage'])->toBe(33.33)
        ->and((float) $transactions[3]['percentage'])->toBe(33.67)
        ->and((float) $response->json('meta.account.balance'))->toBe(-300.0);
});

it('lists split transactions with pending incomes before the origin outcome', function () {
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

    expect($transactions)->toHaveCount(4)
        ->and($transactions->pluck('type')->take(4)->all())->toBe([
            'income',
            'income',
            'income',
            'outcome',
        ])
        ->and($transactions->pluck('status')->take(4)->all())->toBe([
            'pending',
            'pending',
            'pending',
            'completed',
        ]);
});

it('updates split child transactions when editing a parent transaction through the api', function () {
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

    $transactionId = $createResponse->json('data.0.id');

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
        ->assertJsonPath('data.sub_transactions.0.concept', 'Updated dinner - Parte de '.$owner->name)
        ->assertJsonPath('data.sub_transactions.1.concept', 'Updated dinner - Parte de '.$member->name);

    expect(collect($updateResponse->json('data.sub_transactions'))->pluck('amount')->all())->toBe([50, 150])
        ->and(collect($updateResponse->json('data.sub_transactions'))->pluck('percentage')->all())->toBe([25, 75])
        ->and(
            collect($updateResponse->json('data.sub_transactions'))
                ->pluck('scheduled_at')
                ->every(fn (string $scheduledAt): bool => str_starts_with($scheduledAt, '2026-01-20'))
        )->toBeTrue();
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
