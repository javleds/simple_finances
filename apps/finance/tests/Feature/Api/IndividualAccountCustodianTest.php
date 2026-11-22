<?php

declare(strict_types=1);

use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Models\User;
use Tests\Support\AcceptedUserToken;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->account = Account::factory()->create([
        'user_id' => $this->owner->id, 'balance' => 0, 'credit_card' => false, 'virtual' => false,
    ]);
    $this->account->users()->attach($this->owner, ['percentage' => 100]);
    $this->withHeaders(['Authorization' => 'Bearer ' . AcceptedUserToken::generate($this->owner)['token']]);
    $this->income = [
        'type'   => 'income', 'status' => 'completed', 'concept' => 'Deposit',
        'amount' => 1000, 'scheduled_at' => now()->toDateString(),
    ];
});

it('keeps existing money and historical rules through the complete custodian lifecycle', function (): void {
    $path = "/api/accounts/{$this->account->id}";
    $historicalId = $this->postJson("{$path}/transactions", $this->income)->assertCreated()->json('data.id');
    $this->getJson('/api/dashboard/accounts')->assertOk()->assertJsonPath('data.summary.shared_accounts', 0);
    $custodianId = $this->postJson("{$path}/custodians", [
        'name'      => 'Wallet', 'opening_balance' => 25,
        'transfers' => [['user_id' => $this->owner->id, 'amount' => 600]],
    ])->assertCreated()->assertJsonPath('data.custody_amount', 625.0)->json('data.id');
    $secondId = $this->postJson("{$path}/custodians", ['name' => 'Empty wallet'])->assertCreated()->json('data.id');
    $this->getJson('/api/dashboard/accounts')->assertOk()->assertJsonPath('data.summary.shared_accounts', 1);
    expect($this->account->fresh()->balance)->toBe(1025.0)
        ->and($this->account->users()->count())->toBe(1)
        ->and(Transaction::findOrFail($historicalId)->uses_shared_account_rules)->toBeFalse();
    $sharedId = $this->postJson("{$path}/transactions", [...$this->income, 'amount' => 50, 'custodian_user_id' => $custodianId])
        ->assertCreated()->assertJsonPath('data.custodian_user_id', $custodianId)->json('data.id');
    expect(Transaction::findOrFail($sharedId)->uses_shared_account_rules)->toBeTrue();
    $this->deleteJson("{$path}/custodians/{$custodianId}", ['distributions' => []])->assertUnprocessable();
    $this->deleteJson("{$path}/custodians/{$custodianId}", [
        'distributions' => [['user_id' => $this->owner->id, 'amount' => 675]],
    ])->assertOk();
    $this->getJson('/api/dashboard/accounts')->assertOk()->assertJsonPath('data.summary.shared_accounts', 1);
    $this->deleteJson("{$path}/custodians/{$secondId}", ['distributions' => []])->assertOk();
    $this->getJson('/api/dashboard/accounts')->assertOk()->assertJsonPath('data.summary.shared_accounts', 0);
    expect($this->account->fresh()->balance)->toBe(1075.0);
    $personalId = $this->postJson("{$path}/transactions", $this->income)->assertCreated()->json('data.id');
    expect(Transaction::findOrFail($personalId)->uses_shared_account_rules)->toBeFalse()
        ->and(Transaction::findOrFail($personalId)->uses_virtual_custodian_rules)->toBeFalse();
    $this->putJson("{$path}/transactions/{$historicalId}", [...$this->income, 'amount' => 1100])->assertOk();
    expect(Transaction::findOrFail($historicalId)->uses_shared_account_rules)->toBeFalse();
    $this->putJson("{$path}/transactions/{$sharedId}", [...$this->income, 'amount' => 60, 'custodian_user_id' => $custodianId])->assertOk();
    expect(Transaction::findOrFail($sharedId)->uses_shared_account_rules)->toBeTrue()
        ->and(User::findOrFail($custodianId)->virtual_retired_at)->toBeNull();
    $this->getJson('/api/dashboard/accounts')->assertOk()->assertJsonPath('data.summary.shared_accounts', 1);
});

it('keeps reimbursements actionable with one real member and blocks retirement until settlement', function (): void {
    $path = "/api/accounts/{$this->account->id}";
    $custodianId = $this->postJson("{$path}/custodians", ['name' => 'Wallet', 'opening_balance' => 100])
        ->assertCreated()->json('data.id');
    $expense = [
        ...$this->income, 'type' => 'outcome', 'amount' => 30,
        'paid_by_user_id' => $this->owner->id, 'payment_source' => 'member_out_of_pocket',
    ];
    $expenseId = $this->postJson("{$path}/transactions", $expense)->assertCreated()->json('data.id');
    $this->getJson($path)->assertOk()
        ->assertJsonPath('data.pending_reimbursements.0.from_user_id', $custodianId)
        ->assertJsonPath('data.pending_reimbursements.0.to_user_id', $this->owner->id)
        ->assertJsonPath('data.pending_reimbursements.0.amount', 30.0);
    $this->deleteJson("{$path}/custodians/{$custodianId}", [
        'distributions' => [['user_id' => $this->owner->id, 'amount' => 100]],
    ])->assertUnprocessable()->assertJsonValidationErrors('custodian');
    $this->postJson("{$path}/member-transfers", [
        'from_user_id' => $custodianId, 'to_user_id' => $this->owner->id,
        'amount'       => 30, 'action_type' => 'custody_to_user', 'occurred_at' => now()->toDateString(),
    ])->assertCreated();
    $this->deleteJson("{$path}/custodians/{$custodianId}", [
        'distributions' => [['user_id' => $this->owner->id, 'amount' => 70]],
    ])->assertOk();
    expect($this->account->fresh()->balance)->toBe(70.0)
        ->and(Transaction::findOrFail($expenseId)->allocations()->where('user_id', $custodianId)->exists())->toBeFalse();
});

it('uses shared rules when moving into a custodied account and personal rules when moving out', function (): void {
    $path = "/api/accounts/{$this->account->id}";
    $other = Account::factory()->create(['user_id' => $this->owner->id, 'balance' => 0, 'virtual' => false, 'credit_card' => false]);
    $other->users()->attach($this->owner, ['percentage' => 100]);
    $id = $this->postJson("/api/accounts/{$other->id}/transactions", $this->income)->assertCreated()->json('data.id');
    $custodianId = $this->postJson("{$path}/custodians", ['name' => 'Wallet'])->assertCreated()->json('data.id');
    $this->putJson("/api/transactions/{$id}", [...$this->income, 'account_id' => $this->account->id, 'custodian_user_id' => $custodianId])->assertOk();
    expect(Transaction::findOrFail($id)->uses_shared_account_rules)->toBeTrue()
        ->and(Transaction::findOrFail($id)->uses_virtual_custodian_rules)->toBeTrue();
    $this->putJson("/api/transactions/{$id}", [...$this->income, 'account_id' => $other->id])->assertOk();
    expect(Transaction::findOrFail($id)->uses_shared_account_rules)->toBeFalse()
        ->and(Transaction::findOrFail($id)->custodian_user_id)->toBe($this->owner->id);
});

it('rejects account funds without a positive balance and permits a virtual pocket payer', function (): void {
    $path = "/api/accounts/{$this->account->id}";
    $custodianId = $this->postJson("{$path}/custodians", ['name' => 'Wallet'])->assertCreated()->json('data.id');
    $expense = [...$this->income, 'type' => 'outcome', 'amount' => 20, 'payment_source' => 'account_fund'];
    $this->postJson("{$path}/transactions", $expense)->assertUnprocessable()->assertJsonValidationErrors('payment_source');
    $this->assertDatabaseCount('transactions', 0);
    $this->postJson("{$path}/transactions", [...$expense, 'payment_source' => 'member_out_of_pocket', 'paid_by_user_id' => $custodianId])
        ->assertCreated();
    $this->getJson($path)->assertOk()
        ->assertJsonPath('data.pending_reimbursements.0.from_user_id', $this->owner->id)
        ->assertJsonPath('data.pending_reimbursements.0.to_user_id', $custodianId)
        ->assertJsonPath('data.pending_reimbursements.0.amount', 20.0);
    $this->postJson("{$path}/transactions", $expense)->assertUnprocessable();
});
