<?php

declare(strict_types=1);

use App\Dto\TransactionFormDto;
use App\Services\Accounts\BuildAccountMemberSummary;
use App\Services\Accounts\RegisterAccountMemberTransfer;
use App\Services\Transaction\TransactionCreator;
use FinSi\FinanceDomain\Enums\TransactionPaymentSource;
use FinSi\FinanceDomain\Enums\TransactionStatus;
use FinSi\FinanceDomain\Enums\TransactionType;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\AccountMemberLedgerEntry;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Models\TransactionAllocation;
use FinSi\FinanceDomain\Models\User;
use FinSi\FinanceDomain\Money\Decimal;

it('creates a single transaction when no user payments are provided', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id, 'balance' => '1000.00']);
    $account->users()->attach($user->id, ['percentage' => 100]);
    $this->actingAs($user);

    $dto = TransactionFormDto::fromFormArray([
        'type'                => TransactionType::Outcome,
        'status'              => TransactionStatus::Completed,
        'concept'             => 'Solo expense',
        'amount'              => '120.00',
        'account_id'          => $account->id,
        'split_between_users' => false,
        'user_payments'       => [],
        'scheduled_at'        => now(),
        'financial_goal_id'   => null,
    ]);

    $transaction = app(TransactionCreator::class)->execute($dto);

    expect(Transaction::count())->toBe(1)
        ->and($transaction->amount)->toBe('120.00')
        ->and($transaction->type)->toBe(TransactionType::Outcome)
        ->and($transaction->status)->toBe(TransactionStatus::Completed);
});

it('creates allocations and member ledger entries for outcome type with user payments', function () {
    $owner = User::factory()->create();
    $partner = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id, 'balance' => '1000.00']);
    $account->users()->sync([
        $owner->id   => ['percentage' => 50],
        $partner->id => ['percentage' => 50],
    ]);
    $this->actingAs($owner);

    $dto = TransactionFormDto::fromFormArray([
        'type'                => TransactionType::Outcome,
        'status'              => TransactionStatus::Completed,
        'concept'             => 'Shared expense',
        'amount'              => '200.00',
        'account_id'          => $account->id,
        'split_between_users' => true,
        'user_payments'       => [
            ['user_id' => $owner->id, 'percentage' => 25],
            ['user_id' => $partner->id, 'percentage' => 75],
        ],
        'scheduled_at'        => now(),
        'financial_goal_id'   => null,
    ]);

    $mainTransaction = app(TransactionCreator::class)->execute($dto);

    $allocations = TransactionAllocation::query()->orderBy('id')->get();
    $ledgerEntries = AccountMemberLedgerEntry::query()->orderBy('id')->get();

    expect(Transaction::count())->toBe(1)
        ->and($mainTransaction->status)->toBe(TransactionStatus::Completed)
        ->and($mainTransaction->percentage)->toBe('100.00')
        ->and($mainTransaction->paid_by_user_id)->toBe($owner->id)
        ->and($mainTransaction->payment_source->value)->toBe('account_fund')
        ->and($allocations->pluck('amount')->sort()->values()->all())->toBe(['50.00', '150.00'])
        ->and($allocations->pluck('user_id')->sort()->values()->all())->toBe([$owner->id, $partner->id])
        ->and($allocations->pluck('percentage')->sort()->values()->all())->toBe(['25.00', '75.00'])
        ->and($ledgerEntries)->toHaveCount(2)
        ->and($ledgerEntries->pluck('type')->map->value->all())->toBe([
            'account_fund_expense',
            'account_fund_expense',
        ])
        ->and($ledgerEntries->pluck('amount')->all())->toBe(['-50.00', '-150.00']);
});

it('does not create pending incomes for users with zero percentage', function () {
    $owner = User::factory()->create();
    $partner = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id, 'balance' => '0.00']);
    $account->users()->sync([
        $owner->id   => ['percentage' => 100],
        $partner->id => ['percentage' => 0],
    ]);
    $this->actingAs($owner);

    $dto = TransactionFormDto::fromFormArray([
        'type'                => TransactionType::Outcome,
        'status'              => TransactionStatus::Completed,
        'concept'             => 'Shared expense with zero allocation',
        'amount'              => '200.00',
        'account_id'          => $account->id,
        'split_between_users' => true,
        'user_payments'       => [
            ['user_id' => $owner->id, 'percentage' => 100],
            ['user_id' => $partner->id, 'percentage' => 0],
        ],
        'scheduled_at'        => now(),
        'financial_goal_id'   => null,
    ]);

    $mainTransaction = app(TransactionCreator::class)->execute($dto);
    $allocations = TransactionAllocation::query()
        ->where('transaction_id', $mainTransaction->id)
        ->orderBy('id')
        ->get();

    expect($allocations)->toHaveCount(1)
        ->and($allocations->pluck('user_id')->all())->toBe([$owner->id])
        ->and($allocations->pluck('percentage')->all())->toBe(['100.00']);
});

it('records an out of pocket payment by another member without splitting it', function () {
    $owner = User::factory()->create();
    $partner = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id, 'balance' => '3000.00']);
    $account->users()->sync([
        $owner->id   => ['percentage' => 50],
        $partner->id => ['percentage' => 50],
    ]);
    $this->actingAs($owner);

    $transaction = app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'type'                => TransactionType::Outcome,
        'status'              => TransactionStatus::Completed,
        'concept'             => 'Partner paid full expense',
        'amount'              => '2200.00',
        'account_id'          => $account->id,
        'payment_source'      => TransactionPaymentSource::MemberOutOfPocket,
        'paid_by_user_id'     => $partner->id,
        'split_between_users' => false,
        'user_payments'       => [],
        'scheduled_at'        => now(),
        'financial_goal_id'   => null,
    ]));

    $ledgerEntries = AccountMemberLedgerEntry::query()
        ->where('transaction_id', $transaction->id)
        ->orderBy('id')
        ->get();

    expect(TransactionAllocation::query()->where('transaction_id', $transaction->id)->count())->toBe(0)
        ->and(Transaction::query()->where('parent_transaction_id', $transaction->id)->exists())->toBeFalse()
        ->and($ledgerEntries->pluck('user_id')->all())->toBe([$owner->id])
        ->and($ledgerEntries->pluck('type')->map->value->all())->toBe(['custody_reimbursement_due'])
        ->and($ledgerEntries->pluck('amount')->all())->toBe(['-2200.00']);
});

it('only leaves the non payer share open when an out of pocket split expense has no custody', function () {
    $owner = User::factory()->create(['name' => 'Owner']);
    $partner = User::factory()->create(['name' => 'Partner']);
    $account = Account::factory()->create(['user_id' => $owner->id, 'balance' => '0.00']);
    $account->users()->sync([
        $owner->id   => ['percentage' => 50],
        $partner->id => ['percentage' => 50],
    ]);
    $this->actingAs($owner);

    $transaction = app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'type'                => TransactionType::Outcome,
        'status'              => TransactionStatus::Completed,
        'concept'             => 'Owner paid split expense',
        'amount'              => '1000.00',
        'account_id'          => $account->id,
        'payment_source'      => TransactionPaymentSource::MemberOutOfPocket,
        'paid_by_user_id'     => $owner->id,
        'split_between_users' => true,
        'user_payments'       => [
            ['user_id' => $owner->id, 'percentage' => 50],
            ['user_id' => $partner->id, 'percentage' => 50],
        ],
        'scheduled_at'        => now(),
        'financial_goal_id'   => null,
    ]));

    $summary = app(BuildAccountMemberSummary::class)->execute($account->fresh());

    expect(Decimal::normalize($account->fresh()->balance))->toBe('-500.00')
        ->and(Decimal::normalize(Transaction::query()->where('parent_transaction_id', $transaction->id)->sum('amount')))->toBe('500.00')
        ->and($summary['pending_reimbursements'])->toBe([
            [
                'from_user_id'   => $partner->id,
                'from_user_name' => 'Partner',
                'to_user_id'     => $owner->id,
                'to_user_name'   => 'Owner',
                'amount'         => '500.00',
                'action_type'    => 'user_to_user',
                'items'          => [
                    [
                        'transaction_id' => (string) $transaction->id,
                        'concept'        => 'Owner paid split expense',
                        'amount'         => '500.00',
                        'occurred_at'    => $transaction->scheduled_at->toDateString(),
                    ],
                ],
            ],
        ]);
});

it('allocates the exact split total even when decimal divisions leave a remainder', function () {
    $owner = User::factory()->create();
    $partner = User::factory()->create();
    $thirdUser = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id, 'balance' => '1000.00']);
    $account->users()->sync([
        $owner->id     => ['percentage' => '33.33'],
        $partner->id   => ['percentage' => '33.33'],
        $thirdUser->id => ['percentage' => '33.34'],
    ]);
    $this->actingAs($owner);

    $dto = TransactionFormDto::fromFormArray([
        'type'                => TransactionType::Outcome,
        'status'              => TransactionStatus::Completed,
        'concept'             => 'Split remainder',
        'amount'              => '100.00',
        'account_id'          => $account->id,
        'split_between_users' => true,
        'user_payments'       => [
            ['user_id' => $owner->id, 'percentage' => '33.33'],
            ['user_id' => $partner->id, 'percentage' => '33.33'],
            ['user_id' => $thirdUser->id, 'percentage' => '33.34'],
        ],
        'scheduled_at'        => now(),
        'financial_goal_id'   => null,
    ]);

    $mainTransaction = app(TransactionCreator::class)->execute($dto);
    $allocations = TransactionAllocation::query()
        ->where('transaction_id', $mainTransaction->id)
        ->orderBy('id')
        ->get();

    expect($allocations->pluck('amount')->all())->toBe(['33.33', '33.33', '33.34'])
        ->and(Decimal::sum($allocations, 'amount'))->toBe('100.00');
});

it('throws an exception when creating a transaction with non-completed status', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);
    $account->users()->attach($user->id, ['percentage' => 100]);
    $this->actingAs($user);

    $dto = TransactionFormDto::fromFormArray([
        'type'                => TransactionType::Outcome,
        'status'              => TransactionStatus::Pending,
        'concept'             => 'Invalid transaction',
        'amount'              => '100.00',
        'account_id'          => $account->id,
        'split_between_users' => false,
        'user_payments'       => [],
        'scheduled_at'        => now(),
        'financial_goal_id'   => null,
    ]);

    app(TransactionCreator::class)->execute($dto);
})->throws(InvalidArgumentException::class, 'Transactions must have status Completed.');

it('records account deficit shares when an account fund expense exceeds custody', function () {
    $owner = User::factory()->create();
    $partner = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id, 'balance' => '1000.00']);
    $account->users()->sync([
        $owner->id   => ['percentage' => 50],
        $partner->id => ['percentage' => 50],
    ]);
    $this->actingAs($owner);

    app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'type'                => TransactionType::Income,
        'status'              => TransactionStatus::Completed,
        'concept'             => 'Initial fund',
        'amount'              => '1000.00',
        'account_id'          => $account->id,
        'custodian_user_id'   => $owner->id,
        'split_between_users' => false,
        'user_payments'       => [],
        'scheduled_at'        => now(),
        'financial_goal_id'   => null,
    ]));

    $transaction = app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'type'                => TransactionType::Outcome,
        'status'              => TransactionStatus::Completed,
        'concept'             => 'Overfunded groceries',
        'amount'              => '1500.00',
        'account_id'          => $account->id,
        'payment_source'      => TransactionPaymentSource::AccountFund,
        'paid_by_user_id'     => $owner->id,
        'split_between_users' => true,
        'user_payments'       => [
            ['user_id' => $owner->id, 'percentage' => 50],
            ['user_id' => $partner->id, 'percentage' => 50],
        ],
        'scheduled_at'        => now(),
        'financial_goal_id'   => null,
    ]));

    $account->refresh();
    $ledgerEntries = AccountMemberLedgerEntry::query()
        ->where('transaction_id', $transaction->id)
        ->orderBy('id')
        ->get();

    expect(Decimal::normalize($account->balance))->toBe('-500.00')
        ->and($ledgerEntries->pluck('type')->map->value->all())->toBe([
            'account_fund_expense',
            'account_fund_expense',
            'account_deficit_share',
            'account_deficit_share',
        ])
        ->and($ledgerEntries->pluck('amount')->all())->toBe(['-750.00', '-250.00', '-250.00', '-250.00']);
});

it('records custody reimbursement when account fund payer covers a responsible user shortfall', function () {
    $owner = User::factory()->create();
    $partner = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id, 'balance' => '0.00']);
    $account->users()->sync([
        $owner->id   => ['percentage' => 50],
        $partner->id => ['percentage' => 50],
    ]);
    $this->actingAs($partner);

    app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'type'                => TransactionType::Income,
        'status'              => TransactionStatus::Completed,
        'concept'             => 'Owner fund',
        'amount'              => '1000.00',
        'account_id'          => $account->id,
        'custodian_user_id'   => $owner->id,
        'split_between_users' => false,
        'user_payments'       => [],
        'scheduled_at'        => now(),
        'financial_goal_id'   => null,
    ]));
    app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'type'                => TransactionType::Income,
        'status'              => TransactionStatus::Completed,
        'concept'             => 'Partner fund',
        'amount'              => '500.00',
        'account_id'          => $account->id,
        'custodian_user_id'   => $partner->id,
        'split_between_users' => false,
        'user_payments'       => [],
        'scheduled_at'        => now(),
        'financial_goal_id'   => null,
    ]));

    $transaction = app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'type'                => TransactionType::Outcome,
        'status'              => TransactionStatus::Completed,
        'concept'             => 'Partner groceries',
        'amount'              => '700.00',
        'account_id'          => $account->id,
        'payment_source'      => TransactionPaymentSource::AccountFund,
        'paid_by_user_id'     => $partner->id,
        'split_between_users' => false,
        'user_payments'       => [],
        'scheduled_at'        => now(),
        'financial_goal_id'   => null,
    ]));

    $account->refresh();
    $ledgerEntries = AccountMemberLedgerEntry::query()
        ->where('transaction_id', $transaction->id)
        ->orderBy('id')
        ->get();

    expect(Decimal::normalize($account->balance))->toBe('800.00')
        ->and($ledgerEntries->pluck('user_id')->all())->toBe([$partner->id, $owner->id, $owner->id, $partner->id])
        ->and($ledgerEntries->pluck('type')->map->value->all())->toBe([
            'account_fund_expense',
            'account_fund_expense',
            'custody_reimbursement_due',
            'custody_reimbursement_due',
        ])
        ->and($ledgerEntries->pluck('amount')->all())->toBe(['-500.00', '-200.00', '-200.00', '200.00']);
});

it('settles an account deficit by increasing balance and custodian amount', function () {
    $owner = User::factory()->create(['name' => 'Owner']);
    $partner = User::factory()->create(['name' => 'Partner']);
    $account = Account::factory()->create(['user_id' => $owner->id, 'balance' => '0.00']);
    $account->users()->sync([
        $owner->id   => ['percentage' => 50],
        $partner->id => ['percentage' => 50],
    ]);
    $this->actingAs($owner);

    app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'type'                => TransactionType::Income,
        'status'              => TransactionStatus::Completed,
        'concept'             => 'Initial fund',
        'amount'              => '1000.00',
        'account_id'          => $account->id,
        'custodian_user_id'   => $owner->id,
        'split_between_users' => false,
        'user_payments'       => [],
        'scheduled_at'        => now(),
        'financial_goal_id'   => null,
    ]));

    app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'type'                => TransactionType::Outcome,
        'status'              => TransactionStatus::Completed,
        'concept'             => 'Overfunded groceries',
        'amount'              => '1500.00',
        'account_id'          => $account->id,
        'payment_source'      => TransactionPaymentSource::AccountFund,
        'paid_by_user_id'     => $owner->id,
        'split_between_users' => true,
        'user_payments'       => [
            ['user_id' => $owner->id, 'percentage' => 50],
            ['user_id' => $partner->id, 'percentage' => 50],
        ],
        'scheduled_at'        => now(),
        'financial_goal_id'   => null,
    ]));

    app(RegisterAccountMemberTransfer::class)->execute($account, [
        'from_user_id' => $partner->id,
        'to_user_id'   => $owner->id,
        'action_type'  => 'user_to_account',
        'amount'       => '250.00',
        'description'  => 'Partner aporte',
        'occurred_at'  => now(),
    ]);

    $summary = app(BuildAccountMemberSummary::class)->execute($account->fresh());
    $ownerPending = collect($summary['pending_reimbursements'])
        ->first(fn (array $item): bool => (int) $item['from_user_id'] === $owner->id
            && (int) $item['to_user_id'] === $owner->id
            && 'user_to_account' === $item['action_type']);

    expect(Decimal::normalize($account->fresh()->balance))->toBe('-250.00')
        ->and($summary['custody_by_user'])->toContain([
            'user_id'   => $owner->id,
            'user_name' => 'Owner',
            'amount'    => '250.00',
        ])
        ->and($ownerPending)->not->toBeNull()
        ->and($ownerPending['amount'])->toBe('250.00');
});

it('settles a custody reimbursement without changing balance', function () {
    $owner = User::factory()->create(['name' => 'Owner']);
    $partner = User::factory()->create(['name' => 'Partner']);
    $account = Account::factory()->create(['user_id' => $owner->id, 'balance' => '0.00']);
    $account->users()->sync([
        $owner->id   => ['percentage' => 50],
        $partner->id => ['percentage' => 50],
    ]);
    $this->actingAs($partner);

    app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'type'                => TransactionType::Income,
        'status'              => TransactionStatus::Completed,
        'concept'             => 'Owner fund',
        'amount'              => '1000.00',
        'account_id'          => $account->id,
        'custodian_user_id'   => $owner->id,
        'split_between_users' => false,
        'user_payments'       => [],
        'scheduled_at'        => now(),
        'financial_goal_id'   => null,
    ]));
    app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'type'                => TransactionType::Income,
        'status'              => TransactionStatus::Completed,
        'concept'             => 'Partner fund',
        'amount'              => '500.00',
        'account_id'          => $account->id,
        'custodian_user_id'   => $partner->id,
        'split_between_users' => false,
        'user_payments'       => [],
        'scheduled_at'        => now(),
        'financial_goal_id'   => null,
    ]));

    app(TransactionCreator::class)->execute(TransactionFormDto::fromFormArray([
        'type'                => TransactionType::Outcome,
        'status'              => TransactionStatus::Completed,
        'concept'             => 'Partner groceries',
        'amount'              => '700.00',
        'account_id'          => $account->id,
        'payment_source'      => TransactionPaymentSource::AccountFund,
        'paid_by_user_id'     => $partner->id,
        'split_between_users' => false,
        'user_payments'       => [],
        'scheduled_at'        => now(),
        'financial_goal_id'   => null,
    ]));

    app(RegisterAccountMemberTransfer::class)->execute($account, [
        'from_user_id' => $owner->id,
        'to_user_id'   => $partner->id,
        'action_type'  => 'custody_to_user',
        'amount'       => '200.00',
        'description'  => 'Owner reimburses partner',
        'occurred_at'  => now(),
    ]);

    $summary = app(BuildAccountMemberSummary::class)->execute($account->fresh());

    expect(Decimal::normalize($account->fresh()->balance))->toBe('800.00')
        ->and($summary['custody_by_user'])->toContain([
            'user_id'   => $owner->id,
            'user_name' => 'Owner',
            'amount'    => '600.00',
        ])
        ->and($summary['pending_reimbursements'])->toBe([]);
});
