<?php

declare(strict_types=1);

use App\Events\AccountInviteCreated;
use App\Events\AccountInviteCreatingRequested;
use App\Events\SubscriptionSaved;
use App\Events\SubscriptionSaving;
use FinSi\FinanceDomain\Models\AccountInvite;
use FinSi\FinanceDomain\Models\FixedIncome;
use FinSi\FinanceDomain\Models\Subscription;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Models\User;
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
    Event::listen(SubscriptionSaving::class, static fn (): bool => false);
    Event::listen(AccountInviteCreatingRequested::class, static fn (): bool => false);

    $subscription = Subscription::factory()->make();
    $invite = AccountInvite::factory()->make();

    expect($subscription->save())->toBeFalse()
        ->and($invite->save())->toBeFalse()
        ->and($subscription->exists)->toBeFalse()
        ->and($invite->exists)->toBeFalse();
});

it('restores legacy model identifiers and serialized enum names', function (): void {
    $user = User::factory()->create();
    $legacyUser = 'App\\Models\\User';
    $identifier = new Illuminate\Contracts\Database\ModelIdentifier($legacyUser, $user->id, [], $user->getConnectionName());
    $restorer = new class {
        use Illuminate\Queue\SerializesAndRestoresModelIdentifiers;
    };
    $restored = $restorer->restoreModel(unserialize(serialize($identifier)));
    $legacyEnum = 'App\\Enums\\TransactionType:Income';
    $enum = unserialize('E:' . strlen($legacyEnum) . ':"' . $legacyEnum . '";');

    expect($restored)->toBeInstanceOf(User::class)
        ->and($restored->id)->toBe($user->id)
        ->and(get_class(new $legacyUser()))->toBe(User::class)
        ->and($enum)->toBe(FinSi\FinanceDomain\Enums\TransactionType::Income);
});

it('reads historical notifications and writes the same morph identifier', function (): void {
    $user = User::factory()->create();
    $id = (string) Illuminate\Support\Str::uuid();
    Illuminate\Support\Facades\DB::table('notifications')->insert([
        'id'              => $id,
        'type'            => 'historical-notification',
        'notifiable_type' => 'App\\Models\\User',
        'notifiable_id'   => $user->id,
        'data'            => '{}',
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    expect($user->getMorphClass())->toBe('App\\Models\\User')
        ->and($user->notifications()->sole()->id)->toBe($id)
        ->and($user->notifications()->sole()->notifiable)->toBeInstanceOf(User::class);
});

it('renders legacy transaction mail with the locally calculated balance', function (): void {
    $user = User::factory()->create();
    $account = FinSi\FinanceDomain\Models\Account::factory()->create(['user_id' => $user->id, 'credit_card' => false]);
    $account->users()->attach($user->id, ['percentage' => 100]);
    $transaction = Transaction::factory()->create([
        'account_id'   => $account->id,
        'user_id'      => $user->id,
        'amount'       => 123,
        'type'         => FinSi\FinanceDomain\Enums\TransactionType::Income,
        'scheduled_at' => now()->subDay(),
    ]);
    $this->actingAs($user);

    $mail = (new App\Notifications\SharedTransactionChangedEmail(
        $user,
        $transaction,
        FinSi\FinanceDomain\Enums\Action::Created,
    ))->toMail($user);

    expect($mail->viewData['balance'])->toBe(123.0);
    expect((string) $mail->render())->toContain('Balance después del movimiento');
});
