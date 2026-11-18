<?php

declare(strict_types=1);

use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\AccountMemberLedgerEntry;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Models\User;
use Illuminate\Testing\TestResponse;
use Tests\Support\AcceptedUserToken;
use Tests\TestCase;

function virtualTransactionAccount(): array
{
    $owner = User::factory()->create(['name' => 'Diva']);
    $payer = User::factory()->create(['name' => 'Javi']);
    $account = Account::factory()->create([
        'name'        => 'Kali',
        'user_id'     => $owner->id,
        'balance'     => 0,
        'credit_card' => false,
        'virtual'     => false,
    ]);
    $account->users()->sync([$owner->id => ['percentage' => 50], $payer->id => ['percentage' => 50]]);

    return [$account, $owner, $payer];
}

function virtualTransactionHeaders(User $user): array
{
    return ['Authorization' => 'Bearer ' . AcceptedUserToken::generate($user)['token']];
}

function createTransactionCustodian(TestCase $test, Account $account, User $owner, float $balance = 0, string $name = 'Mercadopago'): int
{
    return (int) $test->withHeaders(virtualTransactionHeaders($owner))
        ->postJson("/api/accounts/{$account->id}/custodians", [
            'name'            => $name,
            'opening_balance' => $balance,
        ])->assertCreated()->json('data.id');
}

function virtualExpensePayload(User $owner, User $member, int $payerId, float $amount, array $overrides = []): array
{
    return [
        'type'                => 'outcome',
        'status'              => 'completed',
        'concept'             => 'Expense paid from pocket',
        'amount'              => $amount,
        'paid_by_user_id'     => $payerId,
        'payment_source'      => 'member_out_of_pocket',
        'split_between_users' => true,
        'user_payments'       => [
            ['user_id' => $owner->id, 'percentage' => 50],
            ['user_id' => $member->id, 'percentage' => 50],
        ],
        'scheduled_at'        => now()->toDateString(),
        ...$overrides,
    ];
}

function virtualAccountData(TestCase $test, Account $account, User $viewer): array
{
    return $test->withHeaders(virtualTransactionHeaders($viewer))
        ->getJson("/api/accounts/{$account->id}")->assertOk()->json('data');
}

function virtualDebtAmount(array $data, int $fromUserId, int $toUserId, string $action): float
{
    return (float) collect($data['pending_reimbursements'])
        ->where('from_user_id', $fromUserId)
        ->where('to_user_id', $toUserId)
        ->where('action_type', $action)
        ->sum('amount');
}

function settleVirtualReimbursement(TestCase $test, Account $account, User $actor, int $fromId, int $toId, float $amount, string $actionType = 'custody_to_user'): TestResponse
{
    return $test->withHeaders(virtualTransactionHeaders($actor))
        ->postJson("/api/accounts/{$account->id}/member-transfers", [
            'from_user_id' => $fromId,
            'to_user_id'   => $toId,
            'amount'       => $amount,
            'action_type'  => $actionType,
            'description'  => 'Custodian reimbursement',
            'occurred_at'  => now()->toDateString(),
        ]);
}

it('reimburses the actual pocket payer from virtual custody when another member records the expense', function (): void {
    [$account, $owner, $payer] = virtualTransactionAccount();
    $custodianId = createTransactionCustodian($this, $account, $owner, 10000);
    $transactionId = (int) $this->withHeaders(virtualTransactionHeaders($owner))
        ->postJson("/api/accounts/{$account->id}/transactions", virtualExpensePayload($owner, $payer, $payer->id, 2000))
        ->assertCreated()->json('data.id');

    $data = virtualAccountData($this, $account, $payer);
    expect((float) $data['balance'])->toBe(8000.0)
        ->and(virtualDebtAmount($data, $custodianId, $payer->id, 'custody_to_user'))->toBe(2000.0)
        ->and(virtualDebtAmount($data, $custodianId, $owner->id, 'custody_to_user'))->toBe(0.0)
        ->and(Transaction::findOrFail($transactionId)->allocations()->where('user_id', $custodianId)->exists())->toBeFalse()
        ->and($account->users()->count())->toBe(2);
    $this->withHeaders(virtualTransactionHeaders($payer))
        ->getJson("/api/accounts/{$account->id}/transactions?search=Expense")
        ->assertOk()->assertJsonPath('data.0.current_user_receivable_reimbursement_amount', 2000.0);

    settleVirtualReimbursement($this, $account, $owner, $custodianId, $owner->id, 500)->assertUnprocessable();
    settleVirtualReimbursement($this, $account, $owner, $custodianId, $payer->id, 2000)->assertCreated();
    $settled = virtualAccountData($this, $account, $payer);
    expect((float) $settled['balance'])->toBe(8000.0)
        ->and($settled['pending_reimbursements'])->toBeEmpty()
        ->and((float) collect($settled['custody_by_user'])->firstWhere('user_id', $custodianId)['amount'])->toBe(8000.0);
});

it('allows an empty virtual payer to advance more than its custody without allocating it a share', function (): void {
    [$account, $owner, $member] = virtualTransactionAccount();
    $payerId = createTransactionCustodian($this, $account, $owner);
    $transactionId = (int) $this->withHeaders(virtualTransactionHeaders($owner))
        ->postJson("/api/accounts/{$account->id}/transactions", virtualExpensePayload($owner, $member, $payerId, 2000))
        ->assertCreated()->json('data.id');

    $data = virtualAccountData($this, $account, $owner);
    expect((float) $data['balance'])->toBe(-2000.0)
        ->and(virtualDebtAmount($data, $owner->id, $payerId, 'user_to_user'))->toBe(1000.0)
        ->and(virtualDebtAmount($data, $member->id, $payerId, 'user_to_user'))->toBe(1000.0)
        ->and(Transaction::findOrFail($transactionId)->allocations()->where('user_id', $payerId)->exists())->toBeFalse();
});

it('keeps both custody and uncovered member debts when a different virtual payer advances beyond the pool', function (): void {
    [$account, $owner, $member] = virtualTransactionAccount();
    $custodianId = createTransactionCustodian($this, $account, $owner, 1000);
    $payerId = createTransactionCustodian($this, $account, $owner, 0, 'Nu');
    $this->withHeaders(virtualTransactionHeaders($owner))
        ->postJson("/api/accounts/{$account->id}/transactions", virtualExpensePayload($owner, $member, $payerId, 2000))
        ->assertCreated();

    $data = virtualAccountData($this, $account, $owner);
    expect((float) $data['balance'])->toBe(-1000.0)
        ->and(virtualDebtAmount($data, $custodianId, $payerId, 'custody_to_user'))->toBe(1000.0)
        ->and(virtualDebtAmount($data, $owner->id, $payerId, 'user_to_user'))->toBe(500.0)
        ->and(virtualDebtAmount($data, $member->id, $payerId, 'user_to_user'))->toBe(500.0);

    settleVirtualReimbursement($this, $account, $owner, $custodianId, $payerId, 1000)->assertCreated();
    $settled = virtualAccountData($this, $account, $owner);
    expect(virtualDebtAmount($settled, $custodianId, $payerId, 'custody_to_user'))->toBe(0.0)
        ->and(virtualDebtAmount($settled, $owner->id, $payerId, 'user_to_user'))->toBe(500.0)
        ->and(virtualDebtAmount($settled, $member->id, $payerId, 'user_to_user'))->toBe(500.0)
        ->and((float) $settled['balance'])->toBe(-1000.0);

    settleVirtualReimbursement($this, $account, $owner, $owner->id, $payerId, 500, 'user_to_user')->assertCreated();
    settleVirtualReimbursement($this, $account, $member, $member->id, $payerId, 500, 'user_to_user')->assertCreated();
    $closed = virtualAccountData($this, $account, $owner);
    expect($closed['pending_reimbursements'])->toBeEmpty()
        ->and((float) $closed['balance'])->toBe(0.0);
});

it('rejects foreign retired and allocated virtual participants', function (): void {
    [$account, $owner, $member] = virtualTransactionAccount();
    [$foreignAccount, $foreignOwner] = virtualTransactionAccount();
    $foreignId = createTransactionCustodian($this, $foreignAccount, $foreignOwner);
    $retiredId = createTransactionCustodian($this, $account, $owner);
    $this->withHeaders(virtualTransactionHeaders($owner))
        ->deleteJson("/api/accounts/{$account->id}/custodians/{$retiredId}", ['distributions' => []])->assertOk();

    foreach ([$foreignId, $retiredId] as $payerId) {
        $this->withHeaders(virtualTransactionHeaders($owner))
            ->postJson("/api/accounts/{$account->id}/transactions", virtualExpensePayload($owner, $member, $payerId, 2000))
            ->assertNotFound();
    }

    $activeId = createTransactionCustodian($this, $account, $owner, 0, 'Active virtual payer');
    $this->withHeaders(virtualTransactionHeaders($owner))
        ->postJson("/api/accounts/{$account->id}/transactions", virtualExpensePayload($owner, $member, $activeId, 2000, [
            'user_payments' => [['user_id' => $activeId, 'percentage' => 100]],
        ]))->assertNotFound();
    expect($account->transactions()->count())->toBe(0);
});

it('regenerates virtual custody debts on editing and removes them on deletion', function (): void {
    [$account, $owner, $payer] = virtualTransactionAccount();
    $custodianId = createTransactionCustodian($this, $account, $owner, 10000);
    $id = (int) $this->withHeaders(virtualTransactionHeaders($owner))
        ->postJson("/api/accounts/{$account->id}/transactions", virtualExpensePayload($owner, $payer, $payer->id, 2000))
        ->assertCreated()->json('data.id');

    $this->withHeaders(virtualTransactionHeaders($owner))
        ->putJson("/api/accounts/{$account->id}/transactions/{$id}", virtualExpensePayload($owner, $payer, $payer->id, 3000))
        ->assertOk();
    $updated = virtualAccountData($this, $account, $payer);
    expect((float) $updated['balance'])->toBe(7000.0)
        ->and(virtualDebtAmount($updated, $custodianId, $payer->id, 'custody_to_user'))->toBe(3000.0);

    $this->withHeaders(virtualTransactionHeaders($owner))
        ->deleteJson("/api/accounts/{$account->id}/transactions/{$id}")->assertOk();
    $deleted = virtualAccountData($this, $account, $payer);
    expect((float) $deleted['balance'])->toBe(10000.0)
        ->and($deleted['pending_reimbursements'])->toBeEmpty()
        ->and(AccountMemberLedgerEntry::query()->where('transaction_id', $id)->count())->toBe(0);
});

it('keeps restored custody visible when an expense predating virtual retirement is deleted', function (): void {
    [$account, $owner, $member] = virtualTransactionAccount();
    $custodianId = createTransactionCustodian($this, $account, $owner, 1000);
    $id = (int) $this->withHeaders(virtualTransactionHeaders($owner))
        ->postJson("/api/accounts/{$account->id}/transactions", virtualExpensePayload($owner, $member, $owner->id, 1000, [
            'payment_source' => 'account_fund',
        ]))->assertCreated()->json('data.id');
    $this->withHeaders(virtualTransactionHeaders($owner))
        ->deleteJson("/api/accounts/{$account->id}/custodians/{$custodianId}", ['distributions' => []])->assertOk();
    $this->withHeaders(virtualTransactionHeaders($owner))
        ->deleteJson("/api/accounts/{$account->id}/transactions/{$id}")->assertOk();

    $data = virtualAccountData($this, $account, $owner);
    expect((float) $data['balance'])->toBe(1000.0)
        ->and(User::findOrFail($custodianId)->virtual_retired_at)->toBeNull()
        ->and((float) collect($data['custody_by_user'])->firstWhere('user_id', $custodianId)['amount'])->toBe(1000.0)
        ->and(collect($data['virtual_custodians'])->contains(fn (array $item): bool => (int) $item['id'] === $custodianId))->toBeTrue();
});

it('reserves custody already owed to a pocket payer before covering later expenses', function (): void {
    [$account, $owner, $member] = virtualTransactionAccount();
    $custodianId = createTransactionCustodian($this, $account, $owner, 1000);
    $payerId = createTransactionCustodian($this, $account, $owner, 0, 'Nu');
    $transactionIds = [];

    foreach (['First expense', 'Second expense'] as $concept) {
        $transactionIds[] = (int) $this->withHeaders(virtualTransactionHeaders($owner))
            ->postJson("/api/accounts/{$account->id}/transactions", virtualExpensePayload($owner, $member, $payerId, 800, [
                'concept' => $concept,
            ]))->assertCreated()->json('data.id');
    }

    $data = virtualAccountData($this, $account, $owner);
    $custodyDebt = collect($data['pending_reimbursements'])
        ->first(fn (array $item): bool => (int) $item['from_user_id'] === $custodianId && (int) $item['to_user_id'] === $payerId);
    $items = collect($custodyDebt['items'])->keyBy('transaction_id');

    expect(virtualDebtAmount($data, $custodianId, $payerId, 'custody_to_user'))->toBe(1000.0)
        ->and((float) $items[(string) $transactionIds[0]]['amount'])->toBe(800.0)
        ->and((float) $items[(string) $transactionIds[1]]['amount'])->toBe(200.0)
        ->and(virtualDebtAmount($data, $owner->id, $payerId, 'user_to_user'))->toBe(300.0)
        ->and(virtualDebtAmount($data, $member->id, $payerId, 'user_to_user'))->toBe(300.0)
        ->and((float) $data['balance'])->toBe(-600.0);
});

it('shows and settles both own custody and virtual custody reimbursement for the same payer', function (): void {
    [$account, $owner, $payer] = virtualTransactionAccount();
    $custodianId = createTransactionCustodian($this, $account, $owner, 500);
    $this->withHeaders(virtualTransactionHeaders($owner))
        ->postJson("/api/accounts/{$account->id}/transactions", [
            'type'                => 'income',
            'status'              => 'completed',
            'concept'             => 'Javi custody',
            'amount'              => 500,
            'custodian_user_id'   => $payer->id,
            'split_between_users' => false,
            'scheduled_at'        => now()->toDateString(),
        ])->assertCreated();
    $this->withHeaders(virtualTransactionHeaders($owner))
        ->postJson("/api/accounts/{$account->id}/transactions", virtualExpensePayload($owner, $payer, $payer->id, 1000))
        ->assertCreated();

    $data = virtualAccountData($this, $account, $payer);
    expect(virtualDebtAmount($data, $payer->id, $payer->id, 'custody_to_user'))->toBe(500.0)
        ->and(virtualDebtAmount($data, $custodianId, $payer->id, 'custody_to_user'))->toBe(500.0)
        ->and((float) $data['balance'])->toBe(0.0);

    settleVirtualReimbursement($this, $account, $payer, $payer->id, $payer->id, 500)->assertCreated();
    $partiallySettled = virtualAccountData($this, $account, $payer);
    expect(virtualDebtAmount($partiallySettled, $payer->id, $payer->id, 'custody_to_user'))->toBe(0.0)
        ->and(virtualDebtAmount($partiallySettled, $custodianId, $payer->id, 'custody_to_user'))->toBe(500.0);
    settleVirtualReimbursement($this, $account, $owner, $custodianId, $payer->id, 500)->assertCreated();

    $settled = virtualAccountData($this, $account, $payer);
    expect($settled['pending_reimbursements'])->toBeEmpty()
        ->and((float) collect($settled['custody_by_user'])->firstWhere('user_id', $payer->id)['amount'])->toBe(0.0)
        ->and((float) collect($settled['custody_by_user'])->firstWhere('user_id', $custodianId)['amount'])->toBe(0.0)
        ->and((float) $settled['balance'])->toBe(0.0);
});

it('excludes custodian opening balances from personal money but includes explicit personal contributions', function (): void {
    [$account, $owner] = virtualTransactionAccount();
    $personal = Account::factory()->create([
        'user_id'     => $owner->id,
        'balance'     => 0,
        'credit_card' => false,
        'virtual'     => false,
    ]);
    $personal->users()->attach($owner->id, ['percentage' => 100]);
    $this->withHeaders(virtualTransactionHeaders($owner))->putJson('/api/personal-money/settings', [
        'account_id'      => $personal->id,
        'opening_balance' => 1000,
        'enabled'         => true,
        'start_mode'      => 'now',
        'operation_key'   => (string) Illuminate\Support\Str::uuid(),
    ])->assertOk();
    $custodianId = createTransactionCustodian($this, $account, $owner, 500);
    $this->getJson('/api/personal-money')->assertOk()
        ->assertJsonPath('data.available_balance', 1000.0)
        ->assertJsonPath('data.pending_count', 0);

    $this->postJson("/api/accounts/{$account->id}/transactions", [
        'type'                => 'income',
        'status'              => 'completed',
        'concept'             => 'Personal contribution to Mercadopago',
        'amount'              => 200,
        'custodian_user_id'   => $custodianId,
        'split_between_users' => false,
        'scheduled_at'        => now()->toDateString(),
        'cash_source'         => 'personal',
    ])->assertCreated();
    $this->getJson('/api/personal-money')->assertOk()
        ->assertJsonPath('data.available_balance', 800.0)
        ->assertJsonPath('data.pending_count', 0);
});

it('preserves historical expense rules after adding a virtual custodian while enabling them for new expenses', function (): void {
    [$account, $owner, $payer] = virtualTransactionAccount();
    $this->withHeaders(virtualTransactionHeaders($owner))
        ->postJson("/api/accounts/{$account->id}/transactions", [
            'type'                => 'income',
            'status'              => 'completed',
            'concept'             => 'Existing real custody',
            'amount'              => 1000,
            'custodian_user_id'   => $owner->id,
            'split_between_users' => false,
            'scheduled_at'        => now()->toDateString(),
        ])->assertCreated();
    $payload = virtualExpensePayload($owner, $payer, $payer->id, 200);
    $historicalId = (int) $this->postJson("/api/accounts/{$account->id}/transactions", $payload)
        ->assertCreated()->json('data.id');
    $before = virtualAccountData($this, $account, $owner);
    $summarizeDebts = static fn (array $data): array => collect($data['pending_reimbursements'])
        ->map(static fn (array $item): array => [
            'from'   => $item['from_user_id'],
            'to'     => $item['to_user_id'],
            'action' => $item['action_type'],
            'amount' => (float) $item['amount'],
        ])->sortBy(static fn (array $item): string => $item['action'] . ':' . $item['from'] . ':' . $item['to'])
        ->values()->all();

    createTransactionCustodian($this, $account, $owner);
    $this->putJson("/api/accounts/{$account->id}/transactions/{$historicalId}", [
        ...$payload,
        'concept' => 'Historical expense renamed',
    ])->assertOk();
    $after = virtualAccountData($this, $account, $owner);

    expect($summarizeDebts($after))->toBe($summarizeDebts($before))
        ->and((float) $after['balance'])->toBe((float) $before['balance'])
        ->and(Transaction::findOrFail($historicalId)->uses_virtual_custodian_rules)->toBeFalse();

    $newId = (int) $this->postJson("/api/accounts/{$account->id}/transactions", [
        ...$payload,
        'concept' => 'Expense with virtual custodian rules',
    ])->assertCreated()->json('data.id');
    expect(Transaction::findOrFail($newId)->uses_virtual_custodian_rules)->toBeTrue();
});

it('settles the combined old and new custody obligations for the same participant pair', function (): void {
    [$account, $owner, $payer] = virtualTransactionAccount();
    $this->withHeaders(virtualTransactionHeaders($owner))
        ->postJson("/api/accounts/{$account->id}/transactions", [
            'type'                => 'income',
            'status'              => 'completed',
            'concept'             => 'Existing real custody',
            'amount'              => 1000,
            'custodian_user_id'   => $owner->id,
            'split_between_users' => false,
            'scheduled_at'        => now()->toDateString(),
        ])->assertCreated();
    $this->postJson("/api/accounts/{$account->id}/transactions", virtualExpensePayload($owner, $payer, $payer->id, 200, [
        'concept' => 'Legacy custody obligation',
    ]))->assertCreated();
    createTransactionCustodian($this, $account, $owner);
    $this->postJson("/api/accounts/{$account->id}/transactions", virtualExpensePayload($owner, $payer, $payer->id, 200, [
        'concept' => 'New custody obligation',
    ]))->assertCreated();

    $before = virtualAccountData($this, $account, $owner);
    expect(virtualDebtAmount($before, $owner->id, $payer->id, 'custody_to_user'))->toBe(300.0)
        ->and(virtualDebtAmount($before, $owner->id, $owner->id, 'custody_to_user'))->toBe(100.0);

    settleVirtualReimbursement($this, $account, $owner, $owner->id, $payer->id, 300)->assertCreated();
    $partiallySettled = virtualAccountData($this, $account, $owner);
    expect(virtualDebtAmount($partiallySettled, $owner->id, $payer->id, 'custody_to_user'))->toBe(0.0)
        ->and(virtualDebtAmount($partiallySettled, $owner->id, $owner->id, 'custody_to_user'))->toBe(100.0)
        ->and((float) $partiallySettled['balance'])->toBe(600.0);

    settleVirtualReimbursement($this, $account, $owner, $owner->id, $owner->id, 100)->assertCreated();
    $settled = virtualAccountData($this, $account, $owner);
    expect($settled['pending_reimbursements'])->toBeEmpty()
        ->and((float) collect($settled['custody_by_user'])->firstWhere('user_id', $owner->id)['amount'])->toBe(600.0)
        ->and((float) $settled['balance'])->toBe(600.0);
});
