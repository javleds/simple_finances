<?php

use App\Models\Account;
use App\Models\User;
use App\Notifications\InviteAccountEmail;
use App\Services\AccountInvites\SendInvitationNotification;
use Illuminate\Support\Facades\Notification;

it('sends the invitation email to a registered invited user', function () {
    Notification::fake();

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

    app(SendInvitationNotification::class)->execute($invite);

    Notification::assertSentOnDemand(InviteAccountEmail::class);
});

it('sends the invitation email even when the registered user has no notification preferences', function () {
    Notification::fake();

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

    app(SendInvitationNotification::class)->execute($invite);

    Notification::assertSentOnDemand(InviteAccountEmail::class);
});
