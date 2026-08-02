<?php

use App\Enums\AccountMemberLedgerEntryType;
use App\Models\Account;
use App\Models\AccountLedgerRepair;
use App\Models\AccountMemberLedgerEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Auth\JwtTokenService;

function accountLedgerRepairHeaders(User $user): array
{
    $token = app(JwtTokenService::class)->generate($user)['token'];

    return [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer '.$token,
    ];
}

function accountLedgerRepairSharedAccount(User $owner, User $member, float $balance = 0.0): Account
{
    $account = Account::factory()->create([
        'balance' => $balance,
        'user_id' => $owner->id,
    ]);
    $account->users()->sync([
        $owner->id => ['percentage' => 50],
        $member->id => ['percentage' => 50],
    ]);

    return $account;
}

function accountLedgerRepairTransaction(Account $account, User $creator, string $concept = 'Filos'): Transaction
{
    return Transaction::factory()->outcome()->completed()->create([
        'account_id' => $account->id,
        'user_id' => $creator->id,
        'concept' => $concept,
        'amount' => 200.0,
        'percentage' => 100.0,
        'scheduled_at' => '2026-07-23',
    ]);
}

function accountLedgerRepairSeedDebt(Account $account, Transaction $transaction, User $payer, User $debtor): void
{
    AccountMemberLedgerEntry::query()->create([
        'account_id' => $account->id,
        'user_id' => $payer->id,
        'transaction_id' => $transaction->id,
        'type' => AccountMemberLedgerEntryType::ExpensePaid,
        'amount' => 200.0,
        'description' => $transaction->concept,
        'occurred_at' => $transaction->scheduled_at,
    ]);
    AccountMemberLedgerEntry::query()->create([
        'account_id' => $account->id,
        'user_id' => $payer->id,
        'transaction_id' => $transaction->id,
        'related_user_id' => $payer->id,
        'type' => AccountMemberLedgerEntryType::ExpenseShare,
        'amount' => -100.0,
        'description' => $transaction->concept,
        'occurred_at' => $transaction->scheduled_at,
    ]);
    AccountMemberLedgerEntry::query()->create([
        'account_id' => $account->id,
        'user_id' => $debtor->id,
        'transaction_id' => $transaction->id,
        'related_user_id' => $payer->id,
        'type' => AccountMemberLedgerEntryType::ExpenseShare,
        'amount' => -100.0,
        'description' => $transaction->concept,
        'occurred_at' => $transaction->scheduled_at,
    ]);
}

it('lets any account member apply an audited settlement correction that clears transaction badges', function () {
    $payer = User::factory()->create(['name' => 'Divanny']);
    $debtor = User::factory()->create(['name' => 'Javier']);
    $account = accountLedgerRepairSharedAccount($payer, $debtor, -100.0);
    $transaction = accountLedgerRepairTransaction($account, $debtor, 'Filos');
    accountLedgerRepairSeedDebt($account, $transaction, $payer, $debtor);

    $this
        ->withHeaders(accountLedgerRepairHeaders($debtor))
        ->getJson("/api/accounts/{$account->id}/transactions?search=Filos")
        ->assertOk()
        ->assertJsonPath('data.0.current_user_pending_reimbursement_amount', 100.0);

    $diagnostics = $this
        ->withHeaders(accountLedgerRepairHeaders($debtor))
        ->getJson("/api/accounts/{$account->id}/ledger/diagnostics")
        ->assertOk()
        ->assertJsonPath('data.diagnostics.0.code', 'transaction_open_debt')
        ->assertJsonPath('data.diagnostics.0.suggested_payload.amount', 100.0)
        ->json('data.diagnostics.0.suggested_payload');

    $this
        ->withHeaders(accountLedgerRepairHeaders($debtor))
        ->postJson("/api/accounts/{$account->id}/ledger/repairs", [
            ...$diagnostics,
            'description' => 'Filos ya fue pagado fuera de la app',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'applied')
        ->assertJsonPath('data.repair_type', 'settlement_correction')
        ->assertJsonPath('data.actor_user_id', (string) $debtor->id)
        ->assertJsonPath('data.can_reverse', true);

    expect(AccountLedgerRepair::query()->count())->toBe(1)
        ->and(AccountMemberLedgerEntry::query()
            ->where('transaction_id', $transaction->id)
            ->where('type', AccountMemberLedgerEntryType::SettlementCorrection)
            ->count())->toBe(2);

    $this
        ->withHeaders(accountLedgerRepairHeaders($debtor))
        ->getJson("/api/accounts/{$account->id}/transactions?search=Filos")
        ->assertOk()
        ->assertJsonPath('data.0.current_user_pending_reimbursement_amount', 0.0)
        ->assertJsonPath('data.0.current_user_receivable_reimbursement_amount', 0.0);
});

it('reverses a settlement correction with inverse auditable ledger entries', function () {
    $payer = User::factory()->create(['name' => 'Divanny']);
    $debtor = User::factory()->create(['name' => 'Javier']);
    $account = accountLedgerRepairSharedAccount($payer, $debtor, -100.0);
    $transaction = accountLedgerRepairTransaction($account, $debtor, 'Filos');
    accountLedgerRepairSeedDebt($account, $transaction, $payer, $debtor);

    $repairId = $this
        ->withHeaders(accountLedgerRepairHeaders($debtor))
        ->postJson("/api/accounts/{$account->id}/ledger/repairs", [
            'issue_code' => 'transaction_open_debt',
            'repair_type' => 'settlement_correction',
            'from_user_id' => $debtor->id,
            'to_user_id' => $payer->id,
            'transaction_id' => $transaction->id,
            'amount' => 100.0,
            'description' => 'Filos ya fue pagado fuera de la app',
        ])
        ->assertCreated()
        ->json('data.id');

    $this
        ->withHeaders(accountLedgerRepairHeaders($payer))
        ->postJson("/api/accounts/{$account->id}/ledger/repairs/{$repairId}/reverse")
        ->assertCreated()
        ->assertJsonPath('data.status', 'applied')
        ->assertJsonPath('data.repair_type', 'settlement_correction');

    expect(AccountLedgerRepair::query()->where('status', 'reversed')->count())->toBe(1)
        ->and(AccountLedgerRepair::query()->where('issue_code', 'repair_reversal')->count())->toBe(1)
        ->and(AccountMemberLedgerEntry::query()
            ->where('transaction_id', $transaction->id)
            ->where('type', AccountMemberLedgerEntryType::SettlementCorrection)
            ->count())->toBe(4);

    $this
        ->withHeaders(accountLedgerRepairHeaders($debtor))
        ->getJson("/api/accounts/{$account->id}/transactions?search=Filos")
        ->assertOk()
        ->assertJsonPath('data.0.current_user_pending_reimbursement_amount', 100.0);
});

it('reports positive balances that do not have explicit custody as user-input corrections', function () {
    $owner = User::factory()->create(['name' => 'Divanny']);
    $member = User::factory()->create(['name' => 'Javier']);
    $account = accountLedgerRepairSharedAccount($owner, $member, 500.0);

    $this
        ->withHeaders(accountLedgerRepairHeaders($member))
        ->getJson("/api/accounts/{$account->id}/ledger/diagnostics")
        ->assertOk()
        ->assertJsonPath('data.diagnostics.0.code', 'positive_balance_custody_gap')
        ->assertJsonPath('data.diagnostics.0.mode', 'needs_user_input')
        ->assertJsonPath('data.diagnostics.0.evidence.gap_amount', 500.0);

    $this
        ->withHeaders(accountLedgerRepairHeaders($member))
        ->postJson("/api/accounts/{$account->id}/ledger/repairs", [
            'issue_code' => 'positive_balance_custody_gap',
            'repair_type' => 'custody_correction',
            'user_id' => $owner->id,
            'amount' => 500.0,
            'description' => 'Divanny custodia el saldo inicial',
        ])
        ->assertCreated()
        ->assertJsonPath('data.repair_type', 'custody_correction');

    expect((float) AccountMemberLedgerEntry::query()
        ->where('account_id', $account->id)
        ->where('type', AccountMemberLedgerEntryType::CustodyCorrection)
        ->sum('amount'))->toBe(500.0)
        ->and((float) $account->fresh()->balance)->toBe(500.0);
});
