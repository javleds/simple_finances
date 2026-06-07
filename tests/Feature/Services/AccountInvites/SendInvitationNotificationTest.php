<?php

use App\Models\Account;
use App\Models\NotificationType;
use App\Models\User;
use App\Notifications\InviteAccountEmail;
use App\Services\AccountInvites\SendInvitationNotification;
use Illuminate\Support\Facades\Notification;

function seedInvitationNotificationType(): NotificationType
{
    return NotificationType::factory()->create([
        'name' => NotificationType::INVITATION_NOTIFICATION,
        'description' => 'Invitation emails',
    ]);
}

it('sends the invitation email when the invited user allows that notification', function () {
    Notification::fake();
    $notificationType = seedInvitationNotificationType();

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

    app(SendInvitationNotification::class)->execute($invite);

    Notification::assertSentOnDemand(InviteAccountEmail::class);
});

it('does not send the invitation email when the invited user disabled that notification', function () {
    Notification::fake();
    seedInvitationNotificationType();

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

    Notification::assertNothingSent();
});
