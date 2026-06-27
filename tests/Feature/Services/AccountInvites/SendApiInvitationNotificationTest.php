<?php

use App\Models\Account;
use App\Models\NotificationType;
use App\Models\User;
use App\Notifications\InviteAccountApiEmail;
use App\Services\AccountInvites\SendApiInvitationNotification;
use Illuminate\Support\Facades\Notification;

it('sends the api invitation email with the spa login link', function () {
    config()->set('app.spa_url', 'https://spa.example.test');
    Notification::fake();

    $notificationType = NotificationType::factory()->create([
        'name' => NotificationType::INVITATION_NOTIFICATION,
        'description' => 'Invitation emails',
    ]);

    $owner = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);
    $invitee->notificationTypes()->sync([$notificationType->id]);
    $account = Account::factory()->create(['user_id' => $owner->id]);

    $invite = new \App\Models\AccountInvite([
        'account_id' => $account->id,
        'user_id' => $owner->id,
        'email' => $invitee->email,
        'percentage' => 10,
        'status' => 'pending',
    ]);
    $invite->setRelation('account', $account);
    $invite->setRelation('user', $owner);

    app(SendApiInvitationNotification::class)->execute($invite);

    Notification::assertSentOnDemand(
        InviteAccountApiEmail::class,
        fn (InviteAccountApiEmail $notification): bool => $notification->toMail($invitee)->viewData['link'] === 'https://spa.example.test/login?email=invitee%40example.com&post_auth_action=account-invites',
    );
});

it('sends the api invitation email when the invited email does not belong to a registered user', function () {
    config()->set('app.spa_url', 'https://spa.example.test');
    Notification::fake();

    $owner = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);

    $invite = new \App\Models\AccountInvite([
        'account_id' => $account->id,
        'user_id' => $owner->id,
        'email' => 'external@example.com',
        'percentage' => 10,
        'status' => 'pending',
    ]);
    $invite->setRelation('account', $account);
    $invite->setRelation('user', $owner);

    app(SendApiInvitationNotification::class)->execute($invite);

    Notification::assertSentOnDemand(
        InviteAccountApiEmail::class,
        fn (InviteAccountApiEmail $notification): bool => $notification->toMail($owner)->viewData['link'] === 'https://spa.example.test/register?email=external%40example.com&post_auth_action=account-invites',
    );
});

it('sends the api invitation email even when the registered user has no notification preferences', function () {
    config()->set('app.spa_url', 'https://spa.example.test');
    Notification::fake();

    NotificationType::factory()->create([
        'name' => NotificationType::INVITATION_NOTIFICATION,
        'description' => 'Invitation emails',
    ]);

    $owner = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);
    $account = Account::factory()->create(['user_id' => $owner->id]);

    $invite = new \App\Models\AccountInvite([
        'account_id' => $account->id,
        'user_id' => $owner->id,
        'email' => $invitee->email,
        'percentage' => 10,
        'status' => 'pending',
    ]);
    $invite->setRelation('account', $account);
    $invite->setRelation('user', $owner);

    app(SendApiInvitationNotification::class)->execute($invite);

    Notification::assertSentOnDemand(
        InviteAccountApiEmail::class,
        fn (InviteAccountApiEmail $notification): bool => $notification->toMail($invitee)->viewData['link'] === 'https://spa.example.test/login?email=invitee%40example.com&post_auth_action=account-invites',
    );
});
