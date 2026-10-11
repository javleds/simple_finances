<?php

declare(strict_types=1);

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
        'user_id'    => $owner->id,
        'email'      => $invitee->email,
        'percentage' => 25,
        'status'     => InviteStatus::Pending,
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

it('does not attach members or convert categories when an invitation is declined', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);
    $account->users()->attach($owner->id);
    $invite = AccountInvite::factory()->create([
        'account_id' => $account->id,
        'user_id'    => $owner->id,
        'email'      => $invitee->email,
    ]);
    auth()->login($invitee);
    $notify = Mockery::mock(NotifyOnInteract::class);
    $notify->shouldReceive('execute')->once();
    $enable = Mockery::mock(EnableNotificationForInvitation::class);
    $enable->shouldNotReceive('execute');

    (new Respond($notify, $enable))->execute($invite, InviteStatus::Declined);

    expect($account->fresh()->uses_shared_categories)->toBeFalse()
        ->and($account->users()->where('users.id', $invitee->id)->exists())->toBeFalse();
});

afterEach(function () {
    Mockery::close();
});

it('converts used personal categories when an invitation is accepted', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);
    $account->users()->attach($owner->id);
    $category = App\Models\Category::create(['user_id' => $owner->id, 'name' => 'Food', 'normalized_name' => 'food', 'created_by_user_id' => $owner->id]);
    $transaction = App\Models\Transaction::factory()->create(['account_id' => $account->id, 'user_id' => $owner->id, 'category_id' => $category->id]);
    $invite = AccountInvite::factory()->create(['account_id' => $account->id, 'user_id' => $owner->id, 'email' => $invitee->email]);
    auth()->login($invitee);
    $notify = Mockery::mock(NotifyOnInteract::class);
    $notify->shouldReceive('execute')->once();
    $enable = Mockery::mock(EnableNotificationForInvitation::class);
    $enable->shouldReceive('execute')->once();
    (new Respond($notify, $enable))->execute($invite, InviteStatus::Accepted);
    expect($account->fresh()->uses_shared_categories)->toBeTrue()
        ->and($transaction->fresh()->category_id)->not->toBe($category->id)
        ->and($account->users()->where('users.id', $invitee->id)->exists())->toBeTrue();
});
