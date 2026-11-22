<?php

declare(strict_types=1);

use App\Dto\AccountInviteDto;
use App\Services\AccountInvites\CreateAccountInvite;
use App\Services\AccountInvites\SendApiInvitationNotification;
use FinSi\FinanceDomain\Enums\InviteStatus;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\AccountInvite;
use FinSi\FinanceDomain\Models\User;

it('creates the invite after sending the invitation email', function () {
    $owner = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);

    $sender = Mockery::mock(SendApiInvitationNotification::class);
    $sender->shouldReceive('execute')
        ->once()
        ->with(Mockery::on(function (AccountInvite $invite) use ($account, $owner): bool {
            return $invite->account_id === $account->id
                && $invite->user_id === $owner->id
                && 'invitee@example.com' === $invite->email
                && '25.00' === $invite->percentage
                && InviteStatus::Pending === $invite->status
                && $invite->account->is($account)
                && $invite->user->is($owner);
        }));

    $this->app->instance(SendApiInvitationNotification::class, $sender);

    $invite = app(CreateAccountInvite::class)->execute(new AccountInviteDto(
        account: $account,
        owner: $owner,
        email: 'invitee@example.com',
        percentage: '25.00',
    ));

    expect(AccountInvite::query()->count())->toBe(1)
        ->and($invite->exists)->toBeTrue()
        ->and($invite->account_id)->toBe($account->id)
        ->and($invite->user_id)->toBe($owner->id)
        ->and($invite->email)->toBe('invitee@example.com')
        ->and($invite->percentage)->toBe('25.00')
        ->and($invite->status)->toBe(InviteStatus::Pending);
});

it('does not create the invite when sending the email fails', function () {
    $owner = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);

    $sender = Mockery::mock(SendApiInvitationNotification::class);
    $sender->shouldReceive('execute')
        ->once()
        ->andThrow(new RuntimeException('Mail transport failed.'));

    $this->app->instance(SendApiInvitationNotification::class, $sender);

    app(CreateAccountInvite::class)->execute(new AccountInviteDto(
        account: $account,
        owner: $owner,
        email: 'invitee@example.com',
        percentage: '25.00',
    ));
})->throws(RuntimeException::class, 'Mail transport failed.');

afterEach(function () {
    Mockery::close();
});
