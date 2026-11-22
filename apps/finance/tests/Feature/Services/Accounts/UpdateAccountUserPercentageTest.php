<?php

declare(strict_types=1);

use App\Services\Accounts\UpdateAccountUserPercentage;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\User;
use FinSi\FinanceDomain\Money\Decimal;

it('updates one account user percentage and redistributes the rest proportionally', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $thirdUser = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);
    $account->users()->sync([
        $owner->id     => ['percentage' => 50],
        $member->id    => ['percentage' => 25],
        $thirdUser->id => ['percentage' => 25],
    ]);

    app(UpdateAccountUserPercentage::class)->execute($account, $owner->id, '40.00');

    $users = $account->fresh()->users()->withPivot('percentage')->get();

    expect(Decimal::normalize($users->firstWhere('id', $owner->id)->pivot->percentage))->toBe('40.00')
        ->and(Decimal::normalize($users->firstWhere('id', $member->id)->pivot->percentage))->toBe('30.00')
        ->and(Decimal::normalize($users->firstWhere('id', $thirdUser->id)->pivot->percentage))->toBe('30.00')
        ->and(Decimal::sum($users->map(fn (User $user): string => Decimal::normalize($user->pivot->percentage))))->toBe('100.00');
});

it('updates one account user percentage and distributes the rest evenly when other users have no percentage', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $thirdUser = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);
    $account->users()->sync([
        $owner->id     => ['percentage' => 100],
        $member->id    => ['percentage' => 0],
        $thirdUser->id => ['percentage' => 0],
    ]);

    app(UpdateAccountUserPercentage::class)->execute($account, $owner->id, '50.00');

    $users = $account->fresh()->users()->withPivot('percentage')->get();

    expect(Decimal::normalize($users->firstWhere('id', $owner->id)->pivot->percentage))->toBe('50.00')
        ->and(Decimal::normalize($users->firstWhere('id', $member->id)->pivot->percentage))->toBe('25.00')
        ->and(Decimal::normalize($users->firstWhere('id', $thirdUser->id)->pivot->percentage))->toBe('25.00')
        ->and(Decimal::sum($users->map(fn (User $user): string => Decimal::normalize($user->pivot->percentage))))->toBe('100.00');
});
