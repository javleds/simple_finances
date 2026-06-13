<?php

use App\Enums\InviteStatus;
use App\Models\Account;
use App\Models\AccountInvite;
use App\Models\User;
use App\Services\AccountInvites\EnableNotificationForInvitation;
use App\Services\AccountInvites\NotifyOnInteract;
use App\Services\AccountInvites\Respond;
use Illuminate\Support\Facades\DB;

it('does not duplicate an account user when accepting an invite for an attached user', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);
    $account->users()->attach($invitee->id, ['percentage' => 10]);
    $invite = AccountInvite::factory()->create([
        'account_id' => $account->id,
        'user_id' => $owner->id,
        'email' => $invitee->email,
        'percentage' => 25,
        'status' => InviteStatus::Pending,
    ]);

    auth()->login($invitee);

    $notifyOnInteract = Mockery::mock(NotifyOnInteract::class);
    $notifyOnInteract->shouldReceive('execute')->once()->with(Mockery::type(AccountInvite::class));

    $enableNotificationForInvitation = Mockery::mock(EnableNotificationForInvitation::class);
    $enableNotificationForInvitation->shouldReceive('execute')->once()->with(Mockery::type(AccountInvite::class));

    (new Respond($notifyOnInteract, $enableNotificationForInvitation))->execute($invite, InviteStatus::Accepted);

    $pivot = DB::table('account_user')
        ->where('account_id', $account->id)
        ->where('user_id', $invitee->id);

    expect($pivot->count())->toBe(1)
        ->and((float) $pivot->first()->percentage)->toBe(25.0);
});

afterEach(function () {
    Mockery::close();
});
