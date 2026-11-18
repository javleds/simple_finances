<?php

declare(strict_types=1);

use App\Dto\AccountInviteDto;
use App\Services\AccountInvites\CreateAccountInvite;
use App\Services\AccountInvites\SendApiInvitationNotification;
use App\Services\AccountInvites\SendInvitationNotification;
use App\Services\Auth\JwtTokenService;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\AccountInvite;
use FinSi\FinanceDomain\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Tests\Support\AcceptedUserToken;

function virtualIdentityAccount(): Account
{
    $owner = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);
    $account->users()->attach($owner->id, ['percentage' => 100]);

    return $account;
}

it('rejects login and previously issued tokens for virtual identities', function (bool $retired): void {
    $account = virtualIdentityAccount();
    $user = User::factory()->create(['password' => 'known-password']);
    $token = app(JwtTokenService::class)->generate($user)['token'];
    $user->update(['virtual_account_id' => $account->id, 'virtual_retired_at' => $retired ? now() : null]);

    $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'known-password'])->assertUnprocessable();
    $this->withToken($token)->getJson('/api/profile')->assertUnauthorized();

    expect(fn () => app(JwtTokenService::class)->generate($user))->toThrow(RuntimeException::class);
})->with([false, true]);

it('does not recover reset or verify a virtual identity', function (): void {
    NotificationFacade::fake();
    $account = virtualIdentityAccount();
    $user = User::factory()->unverified()->create(['password' => 'original-password']);
    $resetToken = Password::createToken($user);
    $user->update(['virtual_account_id' => $account->id]);

    $this->postJson('/api/auth/password-recovery', ['email' => $user->email])->assertUnprocessable();
    $this->putJson('/api/auth/password-reset', [
        'email'                 => $user->email,
        'token'                 => $resetToken,
        'password'              => 'replacement-password',
        'password_confirmation' => 'replacement-password',
    ])->assertUnprocessable();
    $this->postJson('/api/auth/email-verification-notification-by-email', ['email' => $user->email])->assertSuccessful();
    $this->getJson(URL::temporarySignedRoute('verification.verify', now()->addMinutes(10), [
        'id'   => $user->id,
        'hash' => sha1($user->email),
    ]))->assertNotFound();

    expect(Hash::check('original-password', $user->fresh()->password))->toBeTrue()
        ->and($user->fresh()->email_verified_at)->toBeNull();
    NotificationFacade::assertNothingSent();
});

it('rejects attaching or inviting a virtual identity into any account', function (bool $sameAccount): void {
    NotificationFacade::fake();
    $account = virtualIdentityAccount();
    $owner = User::findOrFail($account->user_id);
    $virtualAccount = $sameAccount ? $account : virtualIdentityAccount();
    $custodian = User::factory()->create(['virtual_account_id' => $virtualAccount->id]);
    $this->withToken(AcceptedUserToken::generate($owner)['token']);

    $this->postJson("/api/accounts/{$account->id}/users", [
        'user_id'    => $custodian->id,
        'percentage' => 0,
    ])->assertNotFound();
    $this->postJson("/api/accounts/{$account->id}/invites", [
        'email'      => $custodian->email,
        'percentage' => 0,
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->postJson('/api/account-invites', [
        'account_id' => $account->id,
        'email'      => $custodian->email,
        'percentage' => 0,
    ])->assertUnprocessable()->assertJsonValidationErrors('email');

    $invite = AccountInvite::factory()->createQuietly([
        'account_id' => $account->id,
        'user_id'    => $owner->id,
        'email'      => 'person@example.com',
        'status'     => 'pending',
    ]);
    $this->putJson("/api/accounts/{$account->id}/invites/{$invite->id}", [
        'email' => $custodian->email,
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
    expect($invite->fresh()->email)->toBe('person@example.com');

    expect(fn () => app(CreateAccountInvite::class)->execute(new AccountInviteDto(
        account: $account,
        owner: $owner,
        email: $custodian->email,
        percentage: 0,
    )))->toThrow(ValidationException::class);

    expect($custodian->accounts()->exists())->toBeFalse();
    NotificationFacade::assertNothingSent();
})->with([false, true]);

it('does not deliver direct or invitation notifications to virtual identities', function (): void {
    $account = virtualIdentityAccount();
    $custodian = User::factory()->create(['virtual_account_id' => $account->id]);
    $realUser = User::findOrFail($account->user_id);
    $notification = new class extends Notification {
        public function via(object $notifiable): array
        {
            return ['database'];
        }

        public function toArray(object $notifiable): array
        {
            return ['message' => 'Test delivery'];
        }
    };
    $custodian->notify($notification);
    $realUser->notify(clone $notification);

    expect($custodian->notifications()->count())->toBe(0)
        ->and($realUser->notifications()->count())->toBe(1);

    NotificationFacade::fake();
    $invite = new AccountInvite(['email' => $custodian->email, 'account_id' => $account->id]);
    app(SendApiInvitationNotification::class)->execute($invite);
    app(SendInvitationNotification::class)->execute($invite);
    NotificationFacade::assertNothingSent();
});
