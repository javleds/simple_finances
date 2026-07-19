<?php

use App\Dto\TransactionFormDto;
use App\Enums\TransactionPaymentSource;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\User;
use App\Services\Accounts\BuildAccountMemberSummary;
use App\Services\Accounts\RegisterAccountMemberTransfer;
use App\Services\Transaction\TransactionCreator;

it('summarizes out of pocket shared expenses as reimbursements between members', function () {
    $owner = User::factory()->create(['name' => 'Owner']);
    $partner = User::factory()->create(['name' => 'Partner']);
    $account = Account::factory()->create(['user_id' => $owner->id]);
    $account->users()->sync([
        $owner->id => ['percentage' => 50],
        $partner->id => ['percentage' => 50],
    ]);
    $this->actingAs($partner);

    app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'type' => TransactionType::Outcome,
        'status' => TransactionStatus::Completed,
        'concept' => 'Travel dinner',
        'amount' => 1000.0,
        'account_id' => $account->id,
        'payment_source' => TransactionPaymentSource::MemberOutOfPocket,
        'paid_by_user_id' => $partner->id,
        'split_between_users' => true,
        'user_payments' => [
            ['user_id' => $owner->id, 'percentage' => 50],
            ['user_id' => $partner->id, 'percentage' => 50],
        ],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]));

    $summary = app(BuildAccountMemberSummary::class)->execute($account);

    expect($summary['settlements_by_user'])->toMatchArray([
        ['user_id' => $owner->id, 'user_name' => 'Owner', 'amount' => -500.0],
        ['user_id' => $partner->id, 'user_name' => 'Partner', 'amount' => 500.0],
    ])
        ->and($summary['pending_reimbursements'])->toBe([
            [
                'from_user_id' => $owner->id,
                'from_user_name' => 'Owner',
                'to_user_id' => $partner->id,
                'to_user_name' => 'Partner',
                'amount' => 500.0,
            ],
        ]);
});

it('settles reimbursements and updates custody with an internal member transfer', function () {
    $owner = User::factory()->create(['name' => 'Owner']);
    $partner = User::factory()->create(['name' => 'Partner']);
    $account = Account::factory()->create(['user_id' => $owner->id]);
    $account->users()->sync([
        $owner->id => ['percentage' => 50],
        $partner->id => ['percentage' => 50],
    ]);
    $this->actingAs($partner);

    app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'type' => TransactionType::Outcome,
        'status' => TransactionStatus::Completed,
        'concept' => 'Travel dinner',
        'amount' => 1000.0,
        'account_id' => $account->id,
        'payment_source' => TransactionPaymentSource::MemberOutOfPocket,
        'paid_by_user_id' => $partner->id,
        'split_between_users' => true,
        'user_payments' => [
            ['user_id' => $owner->id, 'percentage' => 50],
            ['user_id' => $partner->id, 'percentage' => 50],
        ],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]));

    app(RegisterAccountMemberTransfer::class)->execute($account, [
        'from_user_id' => $owner->id,
        'to_user_id' => $partner->id,
        'amount' => 500.0,
        'description' => 'Dinner reimbursement',
        'occurred_at' => now(),
    ]);

    $summary = app(BuildAccountMemberSummary::class)->execute($account);

    expect($summary['settlements_by_user'])->toMatchArray([
        ['user_id' => $owner->id, 'user_name' => 'Owner', 'amount' => 0.0],
        ['user_id' => $partner->id, 'user_name' => 'Partner', 'amount' => 0.0],
    ])
        ->and($summary['custody_by_user'])->toMatchArray([
            ['user_id' => $owner->id, 'user_name' => 'Owner', 'amount' => -500.0],
            ['user_id' => $partner->id, 'user_name' => 'Partner', 'amount' => 500.0],
        ])
        ->and($summary['pending_reimbursements'])->toBe([]);
});
