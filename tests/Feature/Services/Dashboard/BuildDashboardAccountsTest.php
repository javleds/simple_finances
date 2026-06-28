<?php

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Dashboard\BuildDashboardAccounts;

it('returns dashboard account summary and pending actions for the signed in user', function () {
    $user = User::factory()->create();
    $sharedUser = User::factory()->create();
    $otherUser = User::factory()->create();
    $this->actingAs($user);

    $account = Account::factory()->create([
        'name' => 'Nomina',
        'color' => '#6a4d4d',
        'user_id' => $user->id,
        'virtual' => false,
    ]);
    $sharedAccount = Account::factory()->create([
        'name' => 'Shared',
        'user_id' => $user->id,
        'virtual' => false,
    ]);
    $virtualAccount = Account::factory()->create([
        'name' => 'Virtual',
        'user_id' => $user->id,
        'virtual' => true,
    ]);
    $otherAccount = Account::factory()->create([
        'name' => 'Other',
        'user_id' => $otherUser->id,
        'virtual' => false,
    ]);
    $deletedAccount = Account::factory()->create([
        'name' => 'Deleted',
        'user_id' => $user->id,
        'virtual' => false,
    ]);

    $account->users()->attach($user->id);
    $sharedAccount->users()->attach([$user->id, $sharedUser->id]);
    $virtualAccount->users()->attach($user->id);
    $otherAccount->users()->attach($otherUser->id);
    $deletedAccount->users()->attach($user->id);
    $deletedAccount->delete();

    $pendingTransaction = Transaction::factory()->income()->pending()->create([
        'concept' => 'Pending reimbursement',
        'amount' => 450.5,
        'account_id' => $account->id,
        'user_id' => $user->id,
        'scheduled_at' => '2026-06-06',
    ]);
    Transaction::factory()->income()->pending()->create([
        'amount' => 800,
        'account_id' => $account->id,
        'user_id' => $sharedUser->id,
    ]);
    Transaction::factory()->outcome()->completed()->create([
        'status' => TransactionStatus::Completed,
        'type' => TransactionType::Outcome,
        'amount' => 100,
        'account_id' => $account->id,
        'user_id' => $user->id,
    ]);
    Transaction::factory()->income()->pending()->create([
        'amount' => 999,
        'account_id' => $deletedAccount->id,
        'user_id' => $user->id,
    ]);

    $data = app(BuildDashboardAccounts::class)->execute();

    expect($data['summary'])->toBe([
        'active_accounts' => 2,
        'virtual_accounts' => 1,
        'shared_accounts' => 1,
        'pending_total' => 450.5,
    ])
        ->and($data['pending_actions'])->toHaveCount(1)
        ->and($data['pending_actions'][0])->toBe([
            'id' => 'tx-'.$pendingTransaction->id,
            'account_id' => $account->id,
            'account_name' => 'Nomina',
            'account_color' => '#6a4d4d',
            'concept' => 'Pending reimbursement',
            'amount' => 450.5,
            'date' => '2026-06-06',
        ]);
});
