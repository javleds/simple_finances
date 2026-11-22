<?php

declare(strict_types=1);

use App\Services\Accounts\RemoveAccountUser;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\User;
use FinSi\FinanceDomain\Money\Decimal;

it('adds the deleted user percentage to the oldest remaining account user', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $thirdUser = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);
    $account->users()->attach($owner->id, ['percentage' => 20]);
    $account->users()->attach($member->id, ['percentage' => 30]);
    $account->users()->attach($thirdUser->id, ['percentage' => 50]);

    app(RemoveAccountUser::class)->execute($account, $owner);

    $users = $account->fresh()->users()->withPivot('percentage')->get();

    expect($users->pluck('id')->all())->not->toContain($owner->id)
        ->and(Decimal::normalize($users->firstWhere('id', $member->id)->pivot->percentage))->toBe('50.00')
        ->and(Decimal::normalize($users->firstWhere('id', $thirdUser->id)->pivot->percentage))->toBe('50.00')
        ->and(Decimal::sum($users->map(fn (User $user): string => Decimal::normalize($user->pivot->percentage))))->toBe('100.00');
});

it('keeps the total percentage at one hundred when one user remains', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);
    $account->users()->attach($owner->id, ['percentage' => 40]);
    $account->users()->attach($member->id, ['percentage' => 60]);

    app(RemoveAccountUser::class)->execute($account, $member);

    $users = $account->fresh()->users()->withPivot('percentage')->get();

    expect($users)->toHaveCount(1)
        ->and($users->first()->id)->toBe($owner->id)
        ->and(Decimal::normalize($users->first()->pivot->percentage))->toBe('100.00');
});
