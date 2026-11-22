<?php

use FinSi\FinanceDomain\Money\Decimal;
use FinSi\FinanceDomain\Enums\AccountMemberLedgerEntryType;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\AccountMemberLedgerEntry;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Models\User;

function sharedAccountLifecycleHeaders(User $user): array
{
    $token = \Tests\Support\AcceptedUserToken::generate($user)['token'];

    return [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer '.$token,
    ];
}

function sharedAccountLifecycleAccount(User $custodian, User $member, string $name): Account
{
    $account = Account::factory()->create([
        'name' => $name,
        'user_id' => $custodian->id,
        'balance' => '0.00',
    ]);
    $account->users()->sync([
        $custodian->id => ['percentage' => '50.00'],
        $member->id => ['percentage' => '50.00'],
    ]);

    return $account;
}

function sharedAccountLifecycleSeedIncome($test, Account $account, User $custodian, string|int $amount): int
{
    return (int) $test
        ->withHeaders(sharedAccountLifecycleHeaders($custodian))
        ->postJson("/api/accounts/{$account->id}/transactions", [
            'type' => 'income',
            'status' => 'completed',
            'concept' => 'Initial account funds',
            'amount' => Decimal::normalize($amount),
            'custodian_user_id' => $custodian->id,
            'split_between_users' => false,
            'scheduled_at' => '2026-07-01',
        ])
        ->assertCreated()
        ->json('data.id');
}

function sharedAccountLifecycleCreateSharedExpense(
    $test,
    Account $account,
    User $creator,
    User $custodian,
    User $member,
    string $concept,
    string|int $amount,
    int $paidByUserId,
): int {
    return (int) $test
        ->withHeaders(sharedAccountLifecycleHeaders($creator))
        ->postJson("/api/accounts/{$account->id}/transactions", [
            'type' => 'outcome',
            'status' => 'completed',
            'concept' => $concept,
            'amount' => Decimal::normalize($amount),
            'paid_by_user_id' => $paidByUserId,
            'payment_source' => 'member_out_of_pocket',
            'split_between_users' => true,
            'user_payments' => [
                ['user_id' => $custodian->id, 'percentage' => '50.00'],
                ['user_id' => $member->id, 'percentage' => '50.00'],
            ],
            'scheduled_at' => '2026-07-10',
        ])
        ->assertCreated()
        ->json('data.id');
}

function sharedAccountLifecycleCreateSharedExpenseResponse(
    $test,
    Account $account,
    User $creator,
    User $custodian,
    User $member,
    string $concept,
    string|int $amount,
    int $paidByUserId,
) {
    return $test
        ->withHeaders(sharedAccountLifecycleHeaders($creator))
        ->postJson("/api/accounts/{$account->id}/transactions", [
            'type' => 'outcome',
            'status' => 'completed',
            'concept' => $concept,
            'amount' => Decimal::normalize($amount),
            'paid_by_user_id' => $paidByUserId,
            'payment_source' => 'member_out_of_pocket',
            'split_between_users' => true,
            'user_payments' => [
                ['user_id' => $custodian->id, 'percentage' => '50.00'],
                ['user_id' => $member->id, 'percentage' => '50.00'],
            ],
            'scheduled_at' => '2026-07-10',
        ]);
}

function sharedAccountLifecycleUpdateSharedExpense(
    $test,
    Account $account,
    int $transactionId,
    User $creator,
    User $custodian,
    User $member,
    string $concept,
    string|int $amount,
    int $paidByUserId,
): void {
    $test
        ->withHeaders(sharedAccountLifecycleHeaders($creator))
        ->putJson("/api/accounts/{$account->id}/transactions/{$transactionId}", [
            'type' => 'outcome',
            'status' => 'completed',
            'concept' => $concept,
            'amount' => Decimal::normalize($amount),
            'paid_by_user_id' => $paidByUserId,
            'payment_source' => 'member_out_of_pocket',
            'split_between_users' => true,
            'user_payments' => [
                ['user_id' => $custodian->id, 'percentage' => '50.00'],
                ['user_id' => $member->id, 'percentage' => '50.00'],
            ],
            'scheduled_at' => '2026-07-11',
        ])
        ->assertOk()
        ->assertJsonPath('data.concept', $concept)
        ->assertJsonPath('data.amount', Decimal::normalize($amount))
        ->assertJsonPath('meta.account.balance', $account->fresh()->balance);
}

function sharedAccountLifecycleDeleteTransaction($test, Account $account, User $creator, int $transactionId): void
{
    $test
        ->withHeaders(sharedAccountLifecycleHeaders($creator))
        ->deleteJson("/api/accounts/{$account->id}/transactions/{$transactionId}")
        ->assertOk()
        ->assertJsonPath('meta.account.balance', $account->fresh()->balance);
}

function sharedAccountLifecycleSettle(
    $test,
    Account $account,
    User $actor,
    User $fromUser,
    User $toUser,
    string|int $amount,
    string $actionType = 'user_to_user',
): void {
    $test
        ->withHeaders(sharedAccountLifecycleHeaders($actor))
        ->postJson("/api/accounts/{$account->id}/member-transfers", [
            'from_user_id' => $fromUser->id,
            'to_user_id' => $toUser->id,
            'action_type' => $actionType,
            'amount' => Decimal::normalize($amount),
            'description' => "Reimbursement from {$fromUser->name} to {$toUser->name}",
            'occurred_at' => '2026-07-12',
        ])
        ->assertCreated();
}

function sharedAccountLifecycleAssertTransactionBadge(
    $test,
    Account $account,
    User $viewer,
    string $concept,
    string|int $pending,
    string|int $receivable,
): void {
    $test
        ->withHeaders(sharedAccountLifecycleHeaders($viewer))
        ->getJson("/api/accounts/{$account->id}/transactions?search=".urlencode($concept))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.concept', $concept)
        ->assertJsonPath('data.0.current_user_pending_reimbursement_amount', Decimal::normalize($pending))
        ->assertJsonPath('data.0.current_user_receivable_reimbursement_amount', Decimal::normalize($receivable));
}

function sharedAccountLifecycleAssertTransactionMissing($test, Account $account, User $viewer, string $concept): void
{
    $test
        ->withHeaders(sharedAccountLifecycleHeaders($viewer))
        ->getJson("/api/accounts/{$account->id}/transactions?search=".urlencode($concept))
        ->assertOk()
        ->assertJsonCount(0, 'data');
}

function sharedAccountLifecycleAssertLedgerRow($test, Account $account, User $viewer, string $concept, string|int $balanceAfter): void
{
    $rows = $test
        ->withHeaders(sharedAccountLifecycleHeaders($viewer))
        ->getJson("/api/accounts/{$account->id}/ledger")
        ->assertOk()
        ->json('data');

    $row = collect($rows)->firstWhere('label', $concept);

    expect($row)->not->toBeNull()
        ->and($row['balance_after'])->toBe(Decimal::normalize($balanceAfter))
        ->and($row['allocations'])->not->toBeEmpty()
        ->and($row['custody_after_by_user'])->not->toBeEmpty()
        ->and($row['settlement_after_by_user'])->not->toBeEmpty();
}

function sharedAccountLifecycleAssertExpenseLedger(
    int $transactionId,
    User $payer,
    User $otherUser,
    string|int $amount,
    string|int $payerShare,
    string|int $otherShare,
): void {
    $entries = AccountMemberLedgerEntry::query()
        ->where('transaction_id', $transactionId)
        ->whereIn('type', [
            AccountMemberLedgerEntryType::ExpensePaid,
            AccountMemberLedgerEntryType::ExpenseShare,
        ])
        ->orderBy('id')
        ->get();

    if ($entries->isEmpty()) {
        expect(AccountMemberLedgerEntry::query()
            ->where('transaction_id', $transactionId)
            ->whereIn('type', [
                AccountMemberLedgerEntryType::CustodyReimbursementDue,
                AccountMemberLedgerEntryType::AccountDeficitShare,
                AccountMemberLedgerEntryType::AccountFundExpense,
            ])
            ->count())->toBeGreaterThan(0);

        return;
    }

    expect($entries)->toHaveCount($payerShare > '0.00' ? 3 : 2)
        ->and(Decimal::sum($entries
            ->where('user_id', $payer->id)
            ->where('type', AccountMemberLedgerEntryType::ExpensePaid), 'amount'))->toBe(Decimal::normalize($amount))
        ->and(Decimal::sum($entries
            ->where('user_id', $payer->id)
            ->where('type', AccountMemberLedgerEntryType::ExpenseShare), 'amount'))->toBe(Decimal::negate($payerShare))
        ->and(Decimal::sum($entries
            ->where('user_id', $otherUser->id)
            ->where('type', AccountMemberLedgerEntryType::ExpenseShare), 'amount'))->toBe(Decimal::negate($otherShare));
}

it('keeps a positive ordinary shared account readable while a non custodian expense is edited, deleted and reimbursed', function () {
    $custodian = User::factory()->create(['name' => 'Custodian']);
    $member = User::factory()->create(['name' => 'Member']);
    $account = sharedAccountLifecycleAccount($custodian, $member, 'Positive ordinary account');
    sharedAccountLifecycleSeedIncome($this, $account, $custodian, '1000.00');

    $transactionId = sharedAccountLifecycleCreateSharedExpense(
        $this,
        $account,
        $member,
        $custodian,
        $member,
        'Member pharmacy',
        '200.00',
        $member->id,
    );

    expect($account->fresh()->balance)->toBe('800.00');
    sharedAccountLifecycleAssertExpenseLedger($transactionId, $member, $custodian, '200.00', '0.00', '200.00');
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $custodian, 'Member pharmacy', '200.00', '0.00');
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $member, 'Member pharmacy', '0.00', '100.00');

    sharedAccountLifecycleUpdateSharedExpense(
        $this,
        $account,
        $transactionId,
        $member,
        $custodian,
        $member,
        'Member pharmacy updated',
        '300.00',
        $member->id,
    );

    expect($account->fresh()->balance)->toBe('700.00');
    sharedAccountLifecycleAssertExpenseLedger($transactionId, $member, $custodian, '300.00', '0.00', '300.00');
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $custodian, 'Member pharmacy updated', '300.00', '0.00');
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $member, 'Member pharmacy updated', '0.00', '150.00');

    sharedAccountLifecycleDeleteTransaction($this, $account, $member, $transactionId);

    expect($account->fresh()->balance)->toBe('1000.00')
        ->and(AccountMemberLedgerEntry::query()->where('transaction_id', $transactionId)->count())->toBe(0);
    sharedAccountLifecycleAssertTransactionMissing($this, $account, $custodian, 'Member pharmacy updated');

    $paidTransactionId = sharedAccountLifecycleCreateSharedExpense(
        $this,
        $account,
        $member,
        $custodian,
        $member,
        'Member groceries',
        '300.00',
        $member->id,
    );

    sharedAccountLifecycleSettle($this, $account, $custodian, $custodian, $member, '150.00', 'custody_to_user');
    sharedAccountLifecycleSettle($this, $account, $custodian, $custodian, $custodian, '150.00', 'custody_to_user');

    expect($account->fresh()->balance)->toBe('700.00');
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $custodian, 'Member groceries', '0.00', '0.00');
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $member, 'Member groceries', '0.00', '0.00');
    expect(AccountMemberLedgerEntry::query()
        ->where('transaction_id', $paidTransactionId)
        ->where('type', AccountMemberLedgerEntryType::SettlementTransfer)
        ->count())->toBe(3);
});

it('keeps a receivable shared account consistent when expenses are edited, deleted and paid without a custodian balance', function () {
    $payer = User::factory()->create(['name' => 'Payer']);
    $member = User::factory()->create(['name' => 'Member']);
    $account = sharedAccountLifecycleAccount($payer, $member, 'Receivable account');

    $transactionId = sharedAccountLifecycleCreateSharedExpense(
        $this,
        $account,
        $payer,
        $payer,
        $member,
        'Trip dinner',
        '200.00',
        $payer->id,
    );

    expect($account->fresh()->balance)->toBe('-100.00');
    sharedAccountLifecycleAssertExpenseLedger($transactionId, $payer, $member, '200.00', '100.00', '100.00');
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $payer, 'Trip dinner', '0.00', '100.00');
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $member, 'Trip dinner', '100.00', '0.00');

    sharedAccountLifecycleUpdateSharedExpense(
        $this,
        $account,
        $transactionId,
        $payer,
        $payer,
        $member,
        'Trip dinner updated',
        '300.00',
        $payer->id,
    );

    expect($account->fresh()->balance)->toBe('-150.00');
    sharedAccountLifecycleAssertExpenseLedger($transactionId, $payer, $member, '300.00', '150.00', '150.00');
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $payer, 'Trip dinner updated', '0.00', '150.00');
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $member, 'Trip dinner updated', '150.00', '0.00');

    sharedAccountLifecycleDeleteTransaction($this, $account, $payer, $transactionId);

    expect($account->fresh()->balance)->toBe('0.00')
        ->and(AccountMemberLedgerEntry::query()->where('transaction_id', $transactionId)->count())->toBe(0);
    sharedAccountLifecycleAssertTransactionMissing($this, $account, $member, 'Trip dinner updated');

    sharedAccountLifecycleCreateSharedExpense(
        $this,
        $account,
        $payer,
        $payer,
        $member,
        'Trip tickets',
        '200.00',
        $payer->id,
    );

    sharedAccountLifecycleSettle($this, $account, $member, $member, $payer, '100.00');

    expect($account->fresh()->balance)->toBe('0.00');
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $payer, 'Trip tickets', '0.00', '0.00');
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $member, 'Trip tickets', '0.00', '0.00');
    expect(Transaction::query()
        ->where('account_id', $account->id)
        ->where('type', 'income')
        ->where('concept', 'Reimbursement from Member to Payer: Trip tickets')
        ->whereNotNull('legacy_migrated_at')
        ->exists())->toBeTrue();
});

it('keeps a receivable expense actionable when the creator records another member as payer', function () {
    $creator = User::factory()->create(['name' => 'Creator']);
    $payer = User::factory()->create(['name' => 'Payer']);
    $account = sharedAccountLifecycleAccount($creator, $payer, 'Creator records payer account');

    $response = sharedAccountLifecycleCreateSharedExpenseResponse(
        $this,
        $account,
        $creator,
        $creator,
        $payer,
        'Dinner paid by payer',
        '200.00',
        $payer->id,
    );
    $response
        ->assertCreated()
        ->assertJsonPath('meta.pending_reimbursements.0.from_user_id', $creator->id)
        ->assertJsonPath('meta.pending_reimbursements.0.to_user_id', $payer->id)
        ->assertJsonPath('meta.pending_reimbursements.0.amount', '100.00');
    $transactionId = (int) $response->json('data.id');

    expect($account->fresh()->balance)->toBe('-100.00');
    sharedAccountLifecycleAssertExpenseLedger($transactionId, $payer, $creator, '200.00', '100.00', '100.00');
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $creator, 'Dinner paid by payer', '100.00', '0.00');
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $payer, 'Dinner paid by payer', '0.00', '100.00');

    $this
        ->withHeaders(sharedAccountLifecycleHeaders($creator))
        ->getJson("/api/accounts/{$account->id}")
        ->assertOk()
        ->assertJsonPath('data.pending_reimbursements.0.from_user_id', $creator->id)
        ->assertJsonPath('data.pending_reimbursements.0.to_user_id', $payer->id)
        ->assertJsonPath('data.pending_reimbursements.0.amount', '100.00')
        ->assertJsonPath('data.pending_reimbursements.0.items.0.transaction_id', (string) $transactionId);

    sharedAccountLifecycleSettle($this, $account, $creator, $creator, $payer, '100.00');

    expect($account->fresh()->balance)->toBe('0.00');
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $creator, 'Dinner paid by payer', '0.00', '0.00');
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $payer, 'Dinner paid by payer', '0.00', '0.00');
});

it('keeps a low balance ordinary account consistent when a member expense turns it negative', function () {
    $custodian = User::factory()->create(['name' => 'Low Balance Custodian']);
    $member = User::factory()->create(['name' => 'Low Balance Member']);
    $account = sharedAccountLifecycleAccount($custodian, $member, 'Low balance ordinary account');
    sharedAccountLifecycleSeedIncome($this, $account, $custodian, '50.00');

    $transactionId = sharedAccountLifecycleCreateSharedExpense(
        $this,
        $account,
        $member,
        $custodian,
        $member,
        'Emergency medicine',
        '120.00',
        $member->id,
    );

    expect($account->fresh()->balance)->toBe('-70.00');
    sharedAccountLifecycleAssertExpenseLedger($transactionId, $member, $custodian, '120.00', '35.00', '85.00');
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $custodian, 'Emergency medicine', '50.00', '0.00');
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $member, 'Emergency medicine', '0.00', '0.00');
    sharedAccountLifecycleAssertLedgerRow($this, $account, $custodian, 'Emergency medicine', '-70.00');

    sharedAccountLifecycleUpdateSharedExpense(
        $this,
        $account,
        $transactionId,
        $member,
        $custodian,
        $member,
        'Emergency medicine updated',
        '80.00',
        $member->id,
    );

    expect($account->fresh()->balance)->toBe('-30.00');
    sharedAccountLifecycleAssertExpenseLedger($transactionId, $member, $custodian, '80.00', '15.00', '65.00');
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $custodian, 'Emergency medicine updated', '50.00', '0.00');
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $member, 'Emergency medicine updated', '0.00', '10.00');

    sharedAccountLifecycleDeleteTransaction($this, $account, $member, $transactionId);

    expect($account->fresh()->balance)->toBe('50.00');
    sharedAccountLifecycleAssertTransactionMissing($this, $account, $custodian, 'Emergency medicine updated');

    sharedAccountLifecycleCreateSharedExpense(
        $this,
        $account,
        $member,
        $custodian,
        $member,
        'Emergency hospital',
        '120.00',
        $member->id,
    );

    sharedAccountLifecycleSettle($this, $account, $custodian, $custodian, $custodian, '50.00', 'custody_to_user');

    expect($account->fresh()->balance)->toBe('-70.00');
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $custodian, 'Emergency hospital', '0.00', '0.00');
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $member, 'Emergency hospital', '0.00', '0.00');
});

it('allows account fund expenses to move a shared account into deficit', function () {
    $owner = User::factory()->create(['name' => 'Owner']);
    $member = User::factory()->create(['name' => 'Member']);
    $account = sharedAccountLifecycleAccount($owner, $member, 'No funds account');
    $account->update(['balance' => '0.00']);

    $this
        ->withHeaders(sharedAccountLifecycleHeaders($owner))
        ->postJson("/api/accounts/{$account->id}/transactions", [
            'type' => 'outcome',
            'status' => 'completed',
            'concept' => 'Invalid funds expense',
            'amount' => '50.00',
            'paid_by_user_id' => $owner->id,
            'payment_source' => 'account_fund',
            'split_between_users' => true,
            'user_payments' => [
                ['user_id' => $owner->id, 'percentage' => '50.00'],
                ['user_id' => $member->id, 'percentage' => '50.00'],
            ],
            'scheduled_at' => '2026-07-10',
        ])
        ->assertCreated()
        ->assertJsonPath('meta.account.balance', '-50.00')
        ->assertJsonPath('meta.pending_reimbursements.0.amount', '25.00')
        ->assertJsonPath('meta.pending_reimbursements.0.action_type', 'user_to_account');
});
