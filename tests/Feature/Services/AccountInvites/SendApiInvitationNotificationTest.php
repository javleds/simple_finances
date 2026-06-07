<?php

use App\Models\Account;
use App\Models\NotificationType;
use App\Models\User;
use App\Notifications\InviteAccountApiEmail;
use App\Services\AccountInvites\SendApiInvitationNotification;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;

it('sends the api invitation email without relying on the current filament panel', function () {
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

    Filament::setCurrentPanel(null);

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
        fn (InviteAccountApiEmail $notification): bool => $notification->toMail($invitee)->viewData['link'] === 'https://spa.example.test/account-invites',
    );
});
