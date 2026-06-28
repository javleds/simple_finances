<?php

use App\Models\Account;
use App\Models\User;
use App\Services\Dashboard\BuildDashboardGraph;

it('returns visible accounts ordered for the dashboard graph', function () {
    $user = User::factory()->create();
    $secondaryUser = User::factory()->create();
    $this->actingAs($user);

    $secondAccount = Account::factory()->create([
        'name' => 'Savings',
        'balance' => 1200.5,
        'color' => '#00ffaa',
        'user_id' => $user->id,
        'virtual' => false,
    ]);
    $firstAccount = Account::factory()->create([
        'name' => 'Checking',
        'balance' => -25,
        'color' => '#6a4d4d',
        'user_id' => $user->id,
        'virtual' => false,
    ]);
    $hiddenAccount = Account::factory()->create([
        'name' => 'Hidden',
        'user_id' => $secondaryUser->id,
    ]);
    $deletedAccount = Account::factory()->create([
        'name' => 'Deleted',
        'user_id' => $user->id,
    ]);

    $firstAccount->users()->attach($user->id);
    $secondAccount->users()->attach($user->id);
    $hiddenAccount->users()->attach($secondaryUser->id);
    $deletedAccount->users()->attach($user->id);
    $deletedAccount->delete();

    $data = app(BuildDashboardGraph::class)->execute()->all();

    expect($data)->toBe([
        [
            'account_id' => $firstAccount->id,
            'account_name' => 'Checking',
            'balance' => -25.0,
            'color' => '#6a4d4d',
            'is_virtual' => false,
        ],
        [
            'account_id' => $secondAccount->id,
            'account_name' => 'Savings',
            'balance' => 1200.5,
            'color' => '#00ffaa',
            'is_virtual' => false,
        ],
    ]);
});
