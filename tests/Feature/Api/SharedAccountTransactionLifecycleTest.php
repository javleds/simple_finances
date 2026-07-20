<?php

use App\Enums\AccountMemberLedgerEntryType;
use App\Models\Account;
use App\Models\AccountMemberLedgerEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Auth\JwtTokenService;

function sharedAccountLifecycleHeaders(User $user): array
{
    $token = app(JwtTokenService::class)->generate($user)['token'];

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
    ]);
    $account->users()->sync([
        $custodian->id => ['percentage' => 50],
        $member->id => ['percentage' => 50],
    ]);

    return $account;
}

function sharedAccountLifecycleSeedIncome($test, Account $account, User $custodian, float $amount): int
{
    return (int) $test
        ->withHeaders(sharedAccountLifecycleHeaders($custodian))
        ->postJson("/api/accounts/{$account->id}/transactions", [
            'type' => 'income',
            'status' => 'completed',
            'concept' => 'Initial account funds',
            'amount' => $amount,
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
    float $amount,
    int $paidByUserId,
): int {
    return (int) $test
        ->withHeaders(sharedAccountLifecycleHeaders($creator))
        ->postJson("/api/accounts/{$account->id}/transactions", [
            'type' => 'outcome',
            'status' => 'completed',
            'concept' => $concept,
            'amount' => $amount,
            'paid_by_user_id' => $paidByUserId,
            'payment_source' => 'member_out_of_pocket',
            'split_between_users' => true,
            'user_payments' => [
                ['user_id' => $custodian->id, 'percentage' => 50],
                ['user_id' => $member->id, 'percentage' => 50],
            ],
            'scheduled_at' => '2026-07-10',
        ])
        ->assertCreated()
        ->json('data.id');
}

function sharedAccountLifecycleUpdateSharedExpense(
    $test,
    Account $account,
    int $transactionId,
    User $creator,
    User $custodian,
    User $member,
    string $concept,
    float $amount,
    int $paidByUserId,
): void {
    $test
        ->withHeaders(sharedAccountLifecycleHeaders($creator))
        ->putJson("/api/accounts/{$account->id}/transactions/{$transactionId}", [
            'type' => 'outcome',
            'status' => 'completed',
            'concept' => $concept,
            'amount' => $amount,
            'paid_by_user_id' => $paidByUserId,
            'payment_source' => 'member_out_of_pocket',
            'split_between_users' => true,
            'user_payments' => [
                ['user_id' => $custodian->id, 'percentage' => 50],
                ['user_id' => $member->id, 'percentage' => 50],
            ],
            'scheduled_at' => '2026-07-11',
        ])
        ->assertOk()
        ->assertJsonPath('data.concept', $concept)
        ->assertJsonPath('data.amount', $amount)
        ->assertJsonPath('meta.account.balance', (float) $account->fresh()->balance);
}

function sharedAccountLifecycleDeleteTransaction($test, Account $account, User $creator, int $transactionId): void
{
    $test
        ->withHeaders(sharedAccountLifecycleHeaders($creator))
        ->deleteJson("/api/accounts/{$account->id}/transactions/{$transactionId}")
        ->assertOk()
        ->assertJsonPath('meta.account.balance', (float) $account->fresh()->balance);
}

function sharedAccountLifecycleSettle(
    $test,
    Account $account,
    User $actor,
    User $fromUser,
    User $toUser,
    float $amount,
): void {
    $test
        ->withHeaders(sharedAccountLifecycleHeaders($actor))
        ->postJson("/api/accounts/{$account->id}/member-transfers", [
            'from_user_id' => $fromUser->id,
            'to_user_id' => $toUser->id,
            'amount' => $amount,
            'description' => "Reimbursement from {$fromUser->name} to {$toUser->name}",
            'occurred_at' => '2026-07-12',
        ])
        ->assertCreated()
        ->assertJsonPath('meta.pending_reimbursements', []);
}

function sharedAccountLifecycleAssertTransactionBadge(
    $test,
    Account $account,
    User $viewer,
    string $concept,
    float $pending,
    float $receivable,
): void {
    $test
        ->withHeaders(sharedAccountLifecycleHeaders($viewer))
        ->getJson("/api/accounts/{$account->id}/transactions?search=".urlencode($concept))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.concept', $concept)
        ->assertJsonPath('data.0.current_user_pending_reimbursement_amount', $pending)
        ->assertJsonPath('data.0.current_user_receivable_reimbursement_amount', $receivable);
}

function sharedAccountLifecycleAssertTransactionMissing($test, Account $account, User $viewer, string $concept): void
{
    $test
        ->withHeaders(sharedAccountLifecycleHeaders($viewer))
        ->getJson("/api/accounts/{$account->id}/transactions?search=".urlencode($concept))
        ->assertOk()
        ->assertJsonCount(0, 'data');
}

function sharedAccountLifecycleAssertExpenseLedger(
    int $transactionId,
    User $payer,
    User $otherUser,
    float $amount,
    float $payerShare,
    float $otherShare,
): void {
    $entries = AccountMemberLedgerEntry::query()
        ->where('transaction_id', $transactionId)
        ->whereIn('type', [
            AccountMemberLedgerEntryType::ExpensePaid,
            AccountMemberLedgerEntryType::ExpenseShare,
        ])
        ->orderBy('id')
        ->get();

    expect($entries)->toHaveCount(3)
        ->and(round((float) $entries
            ->where('user_id', $payer->id)
            ->where('type', AccountMemberLedgerEntryType::ExpensePaid)
            ->sum('amount'), 2))->toBe($amount)
        ->and(round((float) $entries
            ->where('user_id', $payer->id)
            ->where('type', AccountMemberLedgerEntryType::ExpenseShare)
            ->sum('amount'), 2))->toBe($payerShare * -1)
        ->and(round((float) $entries
            ->where('user_id', $otherUser->id)
            ->where('type', AccountMemberLedgerEntryType::ExpenseShare)
            ->sum('amount'), 2))->toBe($otherShare * -1);
}

it('keeps a positive ordinary shared account readable while a non custodian expense is edited, deleted and reimbursed', function () {
    $custodian = User::factory()->create(['name' => 'Custodian']);
    $member = User::factory()->create(['name' => 'Member']);
    $account = sharedAccountLifecycleAccount($custodian, $member, 'Positive ordinary account');
    sharedAccountLifecycleSeedIncome($this, $account, $custodian, 1000.0);

    $transactionId = sharedAccountLifecycleCreateSharedExpense(
        $this,
        $account,
        $member,
        $custodian,
        $member,
        'Member pharmacy',
        200.0,
        $member->id,
    );

    expect((float) $account->fresh()->balance)->toBe(800.0);
    sharedAccountLifecycleAssertExpenseLedger($transactionId, $member, $custodian, 200.0, 100.0, 100.0);
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $custodian, 'Member pharmacy', 100.0, 0.0);
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $member, 'Member pharmacy', 0.0, 100.0);

    sharedAccountLifecycleUpdateSharedExpense(
        $this,
        $account,
        $transactionId,
        $member,
        $custodian,
        $member,
        'Member pharmacy updated',
        300.0,
        $member->id,
    );

    expect((float) $account->fresh()->balance)->toBe(700.0);
    sharedAccountLifecycleAssertExpenseLedger($transactionId, $member, $custodian, 300.0, 150.0, 150.0);
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $custodian, 'Member pharmacy updated', 150.0, 0.0);
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $member, 'Member pharmacy updated', 0.0, 150.0);

    sharedAccountLifecycleDeleteTransaction($this, $account, $member, $transactionId);

    expect((float) $account->fresh()->balance)->toBe(1000.0)
        ->and(AccountMemberLedgerEntry::query()->where('transaction_id', $transactionId)->count())->toBe(0);
    sharedAccountLifecycleAssertTransactionMissing($this, $account, $custodian, 'Member pharmacy updated');

    $paidTransactionId = sharedAccountLifecycleCreateSharedExpense(
        $this,
        $account,
        $member,
        $custodian,
        $member,
        'Member groceries',
        300.0,
        $member->id,
    );

    sharedAccountLifecycleSettle($this, $account, $custodian, $custodian, $member, 150.0);

    expect((float) $account->fresh()->balance)->toBe(700.0);
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $custodian, 'Member groceries', 0.0, 0.0);
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $member, 'Member groceries', 0.0, 0.0);
    expect(AccountMemberLedgerEntry::query()
        ->where('transaction_id', $paidTransactionId)
        ->where('type', AccountMemberLedgerEntryType::SettlementTransfer)
        ->count())->toBe(2);
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
        200.0,
        $payer->id,
    );

    expect((float) $account->fresh()->balance)->toBe(-200.0);
    sharedAccountLifecycleAssertExpenseLedger($transactionId, $payer, $member, 200.0, 100.0, 100.0);
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $payer, 'Trip dinner', 0.0, 100.0);
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $member, 'Trip dinner', 100.0, 0.0);

    sharedAccountLifecycleUpdateSharedExpense(
        $this,
        $account,
        $transactionId,
        $payer,
        $payer,
        $member,
        'Trip dinner updated',
        300.0,
        $payer->id,
    );

    expect((float) $account->fresh()->balance)->toBe(-300.0);
    sharedAccountLifecycleAssertExpenseLedger($transactionId, $payer, $member, 300.0, 150.0, 150.0);
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $payer, 'Trip dinner updated', 0.0, 150.0);
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $member, 'Trip dinner updated', 150.0, 0.0);

    sharedAccountLifecycleDeleteTransaction($this, $account, $payer, $transactionId);

    expect((float) $account->fresh()->balance)->toBe(0.0)
        ->and(AccountMemberLedgerEntry::query()->where('transaction_id', $transactionId)->count())->toBe(0);
    sharedAccountLifecycleAssertTransactionMissing($this, $account, $member, 'Trip dinner updated');

    sharedAccountLifecycleCreateSharedExpense(
        $this,
        $account,
        $payer,
        $payer,
        $member,
        'Trip tickets',
        200.0,
        $payer->id,
    );

    sharedAccountLifecycleSettle($this, $account, $member, $member, $payer, 100.0);

    expect((float) $account->fresh()->balance)->toBe(-100.0);
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $payer, 'Trip tickets', 0.0, 0.0);
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $member, 'Trip tickets', 0.0, 0.0);
    expect(Transaction::query()
        ->where('account_id', $account->id)
        ->where('type', 'income')
        ->where('concept', 'Reimbursement from Member to Payer')
        ->exists())->toBeTrue();
});

it('keeps a low balance ordinary account consistent when a member expense turns it negative', function () {
    $custodian = User::factory()->create(['name' => 'Low Balance Custodian']);
    $member = User::factory()->create(['name' => 'Low Balance Member']);
    $account = sharedAccountLifecycleAccount($custodian, $member, 'Low balance ordinary account');
    sharedAccountLifecycleSeedIncome($this, $account, $custodian, 50.0);

    $transactionId = sharedAccountLifecycleCreateSharedExpense(
        $this,
        $account,
        $member,
        $custodian,
        $member,
        'Emergency medicine',
        120.0,
        $member->id,
    );

    expect((float) $account->fresh()->balance)->toBe(-70.0);
    sharedAccountLifecycleAssertExpenseLedger($transactionId, $member, $custodian, 120.0, 60.0, 60.0);
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $custodian, 'Emergency medicine', 60.0, 0.0);
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $member, 'Emergency medicine', 0.0, 60.0);

    sharedAccountLifecycleUpdateSharedExpense(
        $this,
        $account,
        $transactionId,
        $member,
        $custodian,
        $member,
        'Emergency medicine updated',
        80.0,
        $member->id,
    );

    expect((float) $account->fresh()->balance)->toBe(-30.0);
    sharedAccountLifecycleAssertExpenseLedger($transactionId, $member, $custodian, 80.0, 40.0, 40.0);
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $custodian, 'Emergency medicine updated', 40.0, 0.0);
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $member, 'Emergency medicine updated', 0.0, 40.0);

    sharedAccountLifecycleDeleteTransaction($this, $account, $member, $transactionId);

    expect((float) $account->fresh()->balance)->toBe(50.0);
    sharedAccountLifecycleAssertTransactionMissing($this, $account, $custodian, 'Emergency medicine updated');

    sharedAccountLifecycleCreateSharedExpense(
        $this,
        $account,
        $member,
        $custodian,
        $member,
        'Emergency hospital',
        120.0,
        $member->id,
    );

    sharedAccountLifecycleSettle($this, $account, $custodian, $custodian, $member, 60.0);

    expect((float) $account->fresh()->balance)->toBe(-10.0);
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $custodian, 'Emergency hospital', 0.0, 0.0);
    sharedAccountLifecycleAssertTransactionBadge($this, $account, $member, 'Emergency hospital', 0.0, 0.0);
});
