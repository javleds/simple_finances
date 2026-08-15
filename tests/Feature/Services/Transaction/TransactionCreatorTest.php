<?php

use App\Dto\TransactionFormDto;
use App\Enums\TransactionPaymentSource;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountMemberLedgerEntry;
use App\Models\Transaction;
use App\Models\TransactionAllocation;
use App\Models\User;
use App\Services\Accounts\BuildAccountMemberSummary;
use App\Services\Accounts\RegisterAccountMemberTransfer;
use App\Services\Transaction\TransactionCreator;

it('creates a single transaction when no user payments are provided', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id, 'balance' => 1000.0]);
    $account->users()->attach($user->id, ['percentage' => 100]);
    $this->actingAs($user);

    $dto = TransactionFormDto::fromFormArray([
        'type' => TransactionType::Outcome,
        'status' => TransactionStatus::Completed,
        'concept' => 'Solo expense',
        'amount' => 120.0,
        'account_id' => $account->id,
        'split_between_users' => false,
        'user_payments' => [],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]);

    $transaction = app(TransactionCreator::class)->execute($dto);

    expect(Transaction::count())->toBe(1)
        ->and($transaction->amount)->toBe(120.0)
        ->and($transaction->type)->toBe(TransactionType::Outcome)
        ->and($transaction->status)->toBe(TransactionStatus::Completed);
});

it('creates allocations and member ledger entries for outcome type with user payments', function () {
    $owner = User::factory()->create();
    $partner = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id, 'balance' => 1000.0]);
    $account->users()->sync([
        $owner->id => ['percentage' => 50],
        $partner->id => ['percentage' => 50],
    ]);
    $this->actingAs($owner);

    $dto = TransactionFormDto::fromFormArray([
        'type' => TransactionType::Outcome,
        'status' => TransactionStatus::Completed,
        'concept' => 'Shared expense',
        'amount' => 200.0,
        'account_id' => $account->id,
        'split_between_users' => true,
        'user_payments' => [
            ['user_id' => $owner->id, 'percentage' => 25],
            ['user_id' => $partner->id, 'percentage' => 75],
        ],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]);

    $mainTransaction = app(TransactionCreator::class)->execute($dto);

    $allocations = TransactionAllocation::query()->orderBy('id')->get();
    $ledgerEntries = AccountMemberLedgerEntry::query()->orderBy('id')->get();

    expect(Transaction::count())->toBe(1)
        ->and($mainTransaction->status)->toBe(TransactionStatus::Completed)
        ->and($mainTransaction->percentage)->toBe(100.0)
        ->and($mainTransaction->paid_by_user_id)->toBe($owner->id)
        ->and($mainTransaction->payment_source->value)->toBe('account_fund')
        ->and($allocations->pluck('amount')->sort()->values()->all())->toBe([50.0, 150.0])
        ->and($allocations->pluck('user_id')->sort()->values()->all())->toBe([$owner->id, $partner->id])
        ->and($allocations->pluck('percentage')->sort()->values()->all())->toBe([25.0, 75.0])
        ->and($ledgerEntries)->toHaveCount(2)
        ->and($ledgerEntries->pluck('type')->map->value->all())->toBe([
            'account_fund_expense',
            'account_fund_expense',
        ])
        ->and($ledgerEntries->pluck('amount')->all())->toBe([-50.0, -150.0]);
});

it('does not create pending incomes for users with zero percentage', function () {
    $owner = User::factory()->create();
    $partner = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id, 'balance' => 0.0]);
    $account->users()->sync([
        $owner->id => ['percentage' => 100],
        $partner->id => ['percentage' => 0],
    ]);
    $this->actingAs($owner);

    $dto = TransactionFormDto::fromFormArray([
        'type' => TransactionType::Outcome,
        'status' => TransactionStatus::Completed,
        'concept' => 'Shared expense with zero allocation',
        'amount' => 200.0,
        'account_id' => $account->id,
        'split_between_users' => true,
        'user_payments' => [
            ['user_id' => $owner->id, 'percentage' => 100],
            ['user_id' => $partner->id, 'percentage' => 0],
        ],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]);

    $mainTransaction = app(TransactionCreator::class)->execute($dto);
    $allocations = TransactionAllocation::query()
        ->where('transaction_id', $mainTransaction->id)
        ->orderBy('id')
        ->get();

    expect($allocations)->toHaveCount(1)
        ->and($allocations->pluck('user_id')->all())->toBe([$owner->id])
        ->and($allocations->pluck('percentage')->all())->toBe([100.0]);
});

it('records an out of pocket payment by another member without splitting it', function () {
    $owner = User::factory()->create();
    $partner = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id, 'balance' => 3000.0]);
    $account->users()->sync([
        $owner->id => ['percentage' => 50],
        $partner->id => ['percentage' => 50],
    ]);
    $this->actingAs($owner);

    $transaction = app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'type' => TransactionType::Outcome,
        'status' => TransactionStatus::Completed,
        'concept' => 'Partner paid full expense',
        'amount' => 2200.0,
        'account_id' => $account->id,
        'payment_source' => TransactionPaymentSource::MemberOutOfPocket,
        'paid_by_user_id' => $partner->id,
        'split_between_users' => false,
        'user_payments' => [],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]));

    $ledgerEntries = AccountMemberLedgerEntry::query()
        ->where('transaction_id', $transaction->id)
        ->orderBy('id')
        ->get();

    expect(TransactionAllocation::query()->where('transaction_id', $transaction->id)->count())->toBe(0)
        ->and(Transaction::query()->where('parent_transaction_id', $transaction->id)->exists())->toBeFalse()
        ->and($ledgerEntries->pluck('user_id')->all())->toBe([$owner->id])
        ->and($ledgerEntries->pluck('type')->map->value->all())->toBe(['custody_reimbursement_due'])
        ->and($ledgerEntries->pluck('amount')->all())->toBe([-2200.0]);
});

it('allocates the exact split total even when decimal divisions leave a remainder', function () {
    $owner = User::factory()->create();
    $partner = User::factory()->create();
    $thirdUser = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id, 'balance' => 1000.0]);
    $account->users()->sync([
        $owner->id => ['percentage' => 33.33],
        $partner->id => ['percentage' => 33.33],
        $thirdUser->id => ['percentage' => 33.34],
    ]);
    $this->actingAs($owner);

    $dto = TransactionFormDto::fromFormArray([
        'type' => TransactionType::Outcome,
        'status' => TransactionStatus::Completed,
        'concept' => 'Split remainder',
        'amount' => 100.0,
        'account_id' => $account->id,
        'split_between_users' => true,
        'user_payments' => [
            ['user_id' => $owner->id, 'percentage' => 33.33],
            ['user_id' => $partner->id, 'percentage' => 33.33],
            ['user_id' => $thirdUser->id, 'percentage' => 33.34],
        ],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]);

    $mainTransaction = app(TransactionCreator::class)->execute($dto);
    $allocations = TransactionAllocation::query()
        ->where('transaction_id', $mainTransaction->id)
        ->orderBy('id')
        ->get();

    expect($allocations->pluck('amount')->all())->toBe([33.33, 33.33, 33.34])
        ->and(round($allocations->sum('amount'), 2))->toBe(100.0);
});

it('throws an exception when creating a transaction with non-completed status', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);
    $account->users()->attach($user->id, ['percentage' => 100]);
    $this->actingAs($user);

    $dto = TransactionFormDto::fromFormArray([
        'type' => TransactionType::Outcome,
        'status' => TransactionStatus::Pending,
        'concept' => 'Invalid transaction',
        'amount' => 100.0,
        'account_id' => $account->id,
        'split_between_users' => false,
        'user_payments' => [],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]);

    app(TransactionCreator::class)->execute($dto);
})->throws(\InvalidArgumentException::class, 'Transactions must have status Completed.');

it('records account deficit shares when an account fund expense exceeds custody', function () {
    $owner = User::factory()->create();
    $partner = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id, 'balance' => 1000.0]);
    $account->users()->sync([
        $owner->id => ['percentage' => 50],
        $partner->id => ['percentage' => 50],
    ]);
    $this->actingAs($owner);

    app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'type' => TransactionType::Income,
        'status' => TransactionStatus::Completed,
        'concept' => 'Initial fund',
        'amount' => 1000.0,
        'account_id' => $account->id,
        'custodian_user_id' => $owner->id,
        'split_between_users' => false,
        'user_payments' => [],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]));

    $transaction = app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'type' => TransactionType::Outcome,
        'status' => TransactionStatus::Completed,
        'concept' => 'Overfunded groceries',
        'amount' => 1500.0,
        'account_id' => $account->id,
        'payment_source' => TransactionPaymentSource::AccountFund,
        'paid_by_user_id' => $owner->id,
        'split_between_users' => true,
        'user_payments' => [
            ['user_id' => $owner->id, 'percentage' => 50],
            ['user_id' => $partner->id, 'percentage' => 50],
        ],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]));

    $account->refresh();
    $ledgerEntries = AccountMemberLedgerEntry::query()
        ->where('transaction_id', $transaction->id)
        ->orderBy('id')
        ->get();

    expect((float) $account->balance)->toBe(-500.0)
        ->and($ledgerEntries->pluck('type')->map->value->all())->toBe([
            'account_fund_expense',
            'account_fund_expense',
            'account_deficit_share',
            'account_deficit_share',
        ])
        ->and($ledgerEntries->pluck('amount')->all())->toBe([-750.0, -250.0, -250.0, -250.0]);
});

it('records custody reimbursement when account fund payer covers a responsible user shortfall', function () {
    $owner = User::factory()->create();
    $partner = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id, 'balance' => 0.0]);
    $account->users()->sync([
        $owner->id => ['percentage' => 50],
        $partner->id => ['percentage' => 50],
    ]);
    $this->actingAs($partner);

    app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'type' => TransactionType::Income,
        'status' => TransactionStatus::Completed,
        'concept' => 'Owner fund',
        'amount' => 1000.0,
        'account_id' => $account->id,
        'custodian_user_id' => $owner->id,
        'split_between_users' => false,
        'user_payments' => [],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]));
    app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'type' => TransactionType::Income,
        'status' => TransactionStatus::Completed,
        'concept' => 'Partner fund',
        'amount' => 500.0,
        'account_id' => $account->id,
        'custodian_user_id' => $partner->id,
        'split_between_users' => false,
        'user_payments' => [],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]));

    $transaction = app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'type' => TransactionType::Outcome,
        'status' => TransactionStatus::Completed,
        'concept' => 'Partner groceries',
        'amount' => 700.0,
        'account_id' => $account->id,
        'payment_source' => TransactionPaymentSource::AccountFund,
        'paid_by_user_id' => $partner->id,
        'split_between_users' => false,
        'user_payments' => [],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]));

    $account->refresh();
    $ledgerEntries = AccountMemberLedgerEntry::query()
        ->where('transaction_id', $transaction->id)
        ->orderBy('id')
        ->get();

    expect((float) $account->balance)->toBe(800.0)
        ->and($ledgerEntries->pluck('user_id')->all())->toBe([$partner->id, $owner->id, $owner->id, $partner->id])
        ->and($ledgerEntries->pluck('type')->map->value->all())->toBe([
            'account_fund_expense',
            'account_fund_expense',
            'custody_reimbursement_due',
            'custody_reimbursement_due',
        ])
        ->and($ledgerEntries->pluck('amount')->all())->toBe([-500.0, -200.0, -200.0, 200.0]);
});

it('settles an account deficit by increasing balance and custodian amount', function () {
    $owner = User::factory()->create(['name' => 'Owner']);
    $partner = User::factory()->create(['name' => 'Partner']);
    $account = Account::factory()->create(['user_id' => $owner->id, 'balance' => 0.0]);
    $account->users()->sync([
        $owner->id => ['percentage' => 50],
        $partner->id => ['percentage' => 50],
    ]);
    $this->actingAs($owner);

    app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'type' => TransactionType::Income,
        'status' => TransactionStatus::Completed,
        'concept' => 'Initial fund',
        'amount' => 1000.0,
        'account_id' => $account->id,
        'custodian_user_id' => $owner->id,
        'split_between_users' => false,
        'user_payments' => [],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]));

    app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'type' => TransactionType::Outcome,
        'status' => TransactionStatus::Completed,
        'concept' => 'Overfunded groceries',
        'amount' => 1500.0,
        'account_id' => $account->id,
        'payment_source' => TransactionPaymentSource::AccountFund,
        'paid_by_user_id' => $owner->id,
        'split_between_users' => true,
        'user_payments' => [
            ['user_id' => $owner->id, 'percentage' => 50],
            ['user_id' => $partner->id, 'percentage' => 50],
        ],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]));

    app(RegisterAccountMemberTransfer::class)->execute($account, [
        'from_user_id' => $partner->id,
        'to_user_id' => $owner->id,
        'action_type' => 'user_to_account',
        'amount' => 250.0,
        'description' => 'Partner aporte',
        'occurred_at' => now(),
    ]);

    $summary = app(BuildAccountMemberSummary::class)->execute($account->fresh());
    $ownerPending = collect($summary['pending_reimbursements'])
        ->first(fn (array $item): bool => (int) $item['from_user_id'] === $owner->id
            && (int) $item['to_user_id'] === $owner->id
            && $item['action_type'] === 'user_to_account');

    expect((float) $account->fresh()->balance)->toBe(-250.0)
        ->and($summary['custody_by_user'])->toContain([
            'user_id' => $owner->id,
            'user_name' => 'Owner',
            'amount' => 250.0,
        ])
        ->and($ownerPending)->not->toBeNull()
        ->and($ownerPending['amount'])->toBe(250.0);
});

it('settles a custody reimbursement without changing balance', function () {
    $owner = User::factory()->create(['name' => 'Owner']);
    $partner = User::factory()->create(['name' => 'Partner']);
    $account = Account::factory()->create(['user_id' => $owner->id, 'balance' => 0.0]);
    $account->users()->sync([
        $owner->id => ['percentage' => 50],
        $partner->id => ['percentage' => 50],
    ]);
    $this->actingAs($partner);

    app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'type' => TransactionType::Income,
        'status' => TransactionStatus::Completed,
        'concept' => 'Owner fund',
        'amount' => 1000.0,
        'account_id' => $account->id,
        'custodian_user_id' => $owner->id,
        'split_between_users' => false,
        'user_payments' => [],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]));
    app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'type' => TransactionType::Income,
        'status' => TransactionStatus::Completed,
        'concept' => 'Partner fund',
        'amount' => 500.0,
        'account_id' => $account->id,
        'custodian_user_id' => $partner->id,
        'split_between_users' => false,
        'user_payments' => [],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]));

    app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'type' => TransactionType::Outcome,
        'status' => TransactionStatus::Completed,
        'concept' => 'Partner groceries',
        'amount' => 700.0,
        'account_id' => $account->id,
        'payment_source' => TransactionPaymentSource::AccountFund,
        'paid_by_user_id' => $partner->id,
        'split_between_users' => false,
        'user_payments' => [],
        'scheduled_at' => now(),
        'financial_goal_id' => null,
    ]));

    app(RegisterAccountMemberTransfer::class)->execute($account, [
        'from_user_id' => $owner->id,
        'to_user_id' => $partner->id,
        'action_type' => 'custody_to_user',
        'amount' => 200.0,
        'description' => 'Owner reimburses partner',
        'occurred_at' => now(),
    ]);

    $summary = app(BuildAccountMemberSummary::class)->execute($account->fresh());

    expect((float) $account->fresh()->balance)->toBe(800.0)
        ->and($summary['custody_by_user'])->toContain([
            'user_id' => $owner->id,
            'user_name' => 'Owner',
            'amount' => 600.0,
        ])
        ->and($summary['pending_reimbursements'])->toBe([]);
});
