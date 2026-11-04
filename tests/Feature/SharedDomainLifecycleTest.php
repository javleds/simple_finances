<?php

declare(strict_types=1);

use App\Events\AccountInviteCreated;
use App\Events\AccountInviteCreatingRequested;
use App\Events\SubscriptionSaved;
use App\Events\SubscriptionSaving;
use App\Models\AccountInvite;
use App\Models\FixedIncome;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Event;

it('assigns the authenticated owner while preserving explicit transaction authors', function (): void {
    $actor = User::factory()->create();
    $other = User::factory()->create();
    $this->actingAs($actor);

    $income = FixedIncome::factory()->create(['user_id' => $other->id]);
    $explicit = Transaction::factory()->create(['user_id' => $other->id]);
    $implicit = Transaction::factory()->create(['user_id' => null]);

    expect($income->fresh()->user_id)->toBe($actor->id)
        ->and($explicit->fresh()->user_id)->toBe($other->id)
        ->and($implicit->fresh()->user_id)->toBe($actor->id);
});

it('retains explicit owners without an authenticated request', function (): void {
    $owner = User::factory()->create();

    expect(FixedIncome::factory()->create(['user_id' => $owner->id])->fresh()->user_id)
        ->toBe($owner->id);
});

it('dispatches subscription and invitation lifecycle events once', function (): void {
    Event::fake([SubscriptionSaving::class, SubscriptionSaved::class, AccountInviteCreatingRequested::class, AccountInviteCreated::class]);

    Subscription::factory()->create();
    AccountInvite::factory()->create();

    Event::assertDispatchedTimes(SubscriptionSaving::class, 1);
    Event::assertDispatchedTimes(SubscriptionSaved::class, 1);
    Event::assertDispatchedTimes(AccountInviteCreatingRequested::class, 1);
    Event::assertDispatchedTimes(AccountInviteCreated::class, 1);
});

it('allows lifecycle listeners to cancel subscription and invitation persistence', function (): void {
    Event::listen(SubscriptionSaving::class, fn (): bool => false);
    Event::listen(AccountInviteCreatingRequested::class, fn (): bool => false);

    $subscription = Subscription::factory()->make();
    $invite = AccountInvite::factory()->make();

    expect($subscription->save())->toBeFalse()
        ->and($invite->save())->toBeFalse()
        ->and($subscription->exists)->toBeFalse()
        ->and($invite->exists)->toBeFalse();
});
