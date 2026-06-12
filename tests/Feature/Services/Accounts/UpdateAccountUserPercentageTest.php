<?php

use App\Models\Account;
use App\Models\User;
use App\Services\Accounts\UpdateAccountUserPercentage;

it('updates one account user percentage and redistributes the rest proportionally', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $thirdUser = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);
    $account->users()->sync([
        $owner->id => ['percentage' => 50],
        $member->id => ['percentage' => 25],
        $thirdUser->id => ['percentage' => 25],
    ]);

    app(UpdateAccountUserPercentage::class)->execute($account, $owner->id, 40.0);

    $users = $account->fresh()->users()->withPivot('percentage')->get();

    expect((float) $users->firstWhere('id', $owner->id)->pivot->percentage)->toBe(40.0)
        ->and((float) $users->firstWhere('id', $member->id)->pivot->percentage)->toBe(30.0)
        ->and((float) $users->firstWhere('id', $thirdUser->id)->pivot->percentage)->toBe(30.0)
        ->and(round($users->sum(fn (User $user): float => (float) $user->pivot->percentage), 2))->toBe(100.0);
});

it('updates one account user percentage and distributes the rest evenly when other users have no percentage', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $thirdUser = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);
    $account->users()->sync([
        $owner->id => ['percentage' => 100],
        $member->id => ['percentage' => 0],
        $thirdUser->id => ['percentage' => 0],
    ]);

    app(UpdateAccountUserPercentage::class)->execute($account, $owner->id, 50.0);

    $users = $account->fresh()->users()->withPivot('percentage')->get();

    expect((float) $users->firstWhere('id', $owner->id)->pivot->percentage)->toBe(50.0)
        ->and((float) $users->firstWhere('id', $member->id)->pivot->percentage)->toBe(25.0)
        ->and((float) $users->firstWhere('id', $thirdUser->id)->pivot->percentage)->toBe(25.0)
        ->and(round($users->sum(fn (User $user): float => (float) $user->pivot->percentage), 2))->toBe(100.0);
});
