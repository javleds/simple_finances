<?php

use App\Models\Account;
use App\Models\AccountInvite;
use App\Models\Transaction;
use App\Models\User;
use Database\Factories\Scenarios\UserScenarioFactory;

it('creates individual accounts with positive balances and mixed transactions', function () {
    $user = User::factory()->create();

    $accounts = UserScenarioFactory::for($user)
        ->individualAccounts(3)
        ->withMixedTransactions(10)
        ->getAccounts();

    expect($accounts)->toHaveCount(3);

    foreach ($accounts as $account) {
        expect($account->user_id)->toBe($user->id)
            ->and($account->balance)->toBeGreaterThan(0)
            ->and($account->users()->where('users.id', $user->id)->exists())->toBeTrue()
            ->and(Transaction::withoutGlobalScopes()->where('account_id', $account->id)->count())->toBe(10);
    }
});

it('creates shared owned accounts with additional users and mixed transactions', function () {
    $user = User::factory()->create();

    $accounts = UserScenarioFactory::for($user)
        ->sharedOwnedAccounts(1)
        ->withUsers(2)
        ->withMixedTransactions(12)
        ->getAccounts();

    $account = $accounts->sole();
    $memberIds = $account->users()->pluck('users.id');
    $creatorIds = Transaction::withoutGlobalScopes()
        ->where('account_id', $account->id)
        ->whereNull('parent_transaction_id')
        ->pluck('user_id')
        ->unique();

    expect($account->user_id)->toBe($user->id)
        ->and($account->balance)->toBeGreaterThan(0)
        ->and($memberIds)->toHaveCount(3)
        ->and($creatorIds->count())->toBeGreaterThan(1);
});

it('creates shared invited accounts through accepted invitations', function () {
    $user = User::factory()->create();

    $accounts = UserScenarioFactory::for($user)
        ->sharedInvitedAccounts(1)
        ->withUsers(1)
        ->withMixedTransactions(8)
        ->getAccounts();

    $account = $accounts->sole();
    $invite = AccountInvite::withoutGlobalScopes()
        ->where('account_id', $account->id)
        ->where('email', $user->email)
        ->first();

    expect($account->user_id)->not->toBe($user->id)
        ->and($account->users()->where('users.id', $user->id)->exists())->toBeTrue()
        ->and($account->users()->count())->toBe(3)
        ->and($account->balance)->toBeGreaterThan(0)
        ->and($invite)->not->toBeNull()
        ->and($invite->isAccepted())->toBeTrue();
});
