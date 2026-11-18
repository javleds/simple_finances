<?php

declare(strict_types=1);

use App\Services\Accounts\BuildAccountMemberSummary;
use App\Services\Transaction\SyncAccountMemberLedger;
use FinSi\FinanceDomain\Enums\AccountMemberLedgerEntryType;
use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\Transaction;
use FinSi\FinanceDomain\Models\User;

function custodianHeaders(User $user): array
{
    return ['Authorization' => 'Bearer ' . Tests\Support\AcceptedUserToken::generate($user)['token']];
}

function custodianAccount(): array
{
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id, 'balance' => 10000, 'credit_card' => false, 'virtual' => false]);
    $account->users()->sync([$owner->id => ['percentage' => 50], $member->id => ['percentage' => 50]]);
    $income = Transaction::factory()->income()->completed()->create([
        'account_id'                => $account->id,
        'user_id'                   => $owner->id,
        'custodian_user_id'         => $owner->id,
        'amount'                    => 10000,
        'scheduled_at'              => now(),
        'uses_shared_account_rules' => true,
    ]);
    app(SyncAccountMemberLedger::class)->execute($income);

    return [$account, $owner, $member];
}

it('creates a scoped financial custodian and only adds the new opening balance', function () {
    [$account, $owner] = custodianAccount();
    $response = $this->withHeaders(custodianHeaders($owner))->postJson("/api/accounts/{$account->id}/custodians", [
        'name'            => 'Mercadopago',
        'opening_balance' => 125.25,
        'transfers'       => [['user_id' => $owner->id, 'amount' => 2300.01]],
    ])->assertCreated()->assertJsonPath('data.name', 'Mercadopago')->assertJsonPath('data.custody_amount', 2425.26);

    $custodian = User::findOrFail($response->json('data.id'));
    $opening = Transaction::query()->where('custodian_user_id', $custodian->id)->sole();
    expect($opening->virtual_custodian_opening)->toBeTrue()
        ->and($opening->uses_virtual_custodian_rules)->toBeTrue();
    expect($custodian->virtual_account_id)->toBe($account->id)
        ->and($custodian->email)->toEndWith('@custodians.invalid')
        ->and($account->users()->whereKey($custodian->id)->exists())->toBeFalse()
        ->and($account->fresh()->balance)->toBe(10125.25);
    $summary = app(BuildAccountMemberSummary::class)->execute($account);
    expect(collect($summary['custody_by_user'])->firstWhere('user_id', $owner->id)['amount'])->toBe(7699.99);
    $this->assertDatabaseCount('account_member_ledger_entries', 4);
});

it('creates an empty custodian without changing balance or ledger', function () {
    [$account, $owner] = custodianAccount();
    $this->withHeaders(custodianHeaders($owner))->postJson("/api/accounts/{$account->id}/custodians", ['name' => 'Nu'])
        ->assertCreated()->assertJsonPath('data.custody_amount', 0.0);
    expect($account->fresh()->balance)->toBe(10000.0);
    $this->assertDatabaseCount('account_member_ledger_entries', 1);
});

it('keeps all custody in the account when retiring a custodian and preserves its history', function () {
    [$account, $owner, $member] = custodianAccount();
    $headers = custodianHeaders($owner);
    $id = $this->withHeaders($headers)->postJson("/api/accounts/{$account->id}/custodians", [
        'name' => 'Mercadopago', 'transfers' => [['user_id' => $owner->id, 'amount' => 10000]],
    ])->assertCreated()->json('data.id');

    $this->withHeaders($headers)->deleteJson("/api/accounts/{$account->id}/custodians/{$id}", [
        'distributions' => [['user_id' => $owner->id, 'amount' => 3333.33], ['user_id' => $member->id, 'amount' => 6666.67]],
    ])->assertOk();

    expect(User::findOrFail($id)->virtual_retired_at)->not->toBeNull()
        ->and($account->fresh()->balance)->toBe(10000.0);
    $summary = app(BuildAccountMemberSummary::class)->execute($account);
    $custody = collect($summary['custody_by_user'])->keyBy('user_id');
    expect($custody[$id]['amount'])->toBe(0.0)
        ->and($custody[$owner->id]['amount'])->toBe(3333.33)
        ->and($custody[$member->id]['amount'])->toBe(6666.67)
        ->and($account->memberLedgerEntries()->where('user_id', $id)->count())->toBe(3);
    $this->withHeaders($headers)->getJson("/api/accounts/{$account->id}/custodians")->assertOk()->assertJsonCount(0, 'data');
    $this->withHeaders($headers)->deleteJson("/api/accounts/{$account->id}/custodians/{$id}", ['distributions' => []])->assertNotFound();
});

it('requires exactly all custody before retiring without partial writes', function (float $amount) {
    [$account, $owner] = custodianAccount();
    $headers = custodianHeaders($owner);
    $id = $this->withHeaders($headers)->postJson("/api/accounts/{$account->id}/custodians", [
        'name' => 'Mercadopago', 'transfers' => [['user_id' => $owner->id, 'amount' => 100]],
    ])->json('data.id');
    $this->withHeaders($headers)->deleteJson("/api/accounts/{$account->id}/custodians/{$id}", [
        'distributions' => [['user_id' => $owner->id, 'amount' => $amount]],
    ])->assertUnprocessable()->assertJsonValidationErrors('distributions');
    expect(User::findOrFail($id)->virtual_retired_at)->toBeNull();
    $this->assertDatabaseCount('account_member_ledger_entries', 3);
})->with([99.99, 100.01]);

it('rejects excess custody and participants from other accounts atomically', function () {
    [$account, $owner] = custodianAccount();
    $outsider = User::factory()->create();
    foreach ([[$owner->id, 10000.01], [$outsider->id, 1]] as [$id, $amount]) {
        $this->withHeaders(custodianHeaders($owner))->postJson("/api/accounts/{$account->id}/custodians", [
            'name' => 'Nu', 'opening_balance' => 50, 'transfers' => [['user_id' => $id, 'amount' => $amount]],
        ])->assertUnprocessable()->assertJsonValidationErrors('transfers');
    }
    expect($account->virtualCustodians()->count())->toBe(0);
    $this->assertDatabaseCount('transactions', 1);
    $this->assertDatabaseCount('account_member_ledger_entries', 1);
});

it('limits custody changes to the owner while allowing member reads', function () {
    [$account, $owner, $member] = custodianAccount();
    $id = $this->withHeaders(custodianHeaders($owner))->postJson("/api/accounts/{$account->id}/custodians", ['name' => 'Nu'])->json('data.id');
    $this->withHeaders(custodianHeaders($member))->getJson("/api/accounts/{$account->id}/custodians")->assertOk()->assertJsonCount(1, 'data');
    $this->withHeaders(custodianHeaders($member))->postJson("/api/accounts/{$account->id}/custodians", ['name' => 'Nu'])->assertForbidden();
    $this->withHeaders(custodianHeaders($member))->deleteJson("/api/accounts/{$account->id}/custodians/{$id}", ['distributions' => []])->assertForbidden();
    $this->withHeaders(custodianHeaders(User::factory()->create()))->getJson("/api/accounts/{$account->id}/custodians")->assertForbidden();
});

it('rejects foreign custodian retirement and nonmember distribution destinations', function () {
    [$account, $owner] = custodianAccount();
    [$otherAccount, $otherOwner] = custodianAccount();
    $id = $this->withHeaders(custodianHeaders($otherOwner))->postJson("/api/accounts/{$otherAccount->id}/custodians", ['name' => 'Nu', 'opening_balance' => 100])->json('data.id');
    $this->withHeaders(custodianHeaders($owner))->deleteJson("/api/accounts/{$account->id}/custodians/{$id}", ['distributions' => []])->assertNotFound();
    $this->withHeaders(custodianHeaders($otherOwner))->deleteJson("/api/accounts/{$otherAccount->id}/custodians/{$id}", [
        'distributions' => [['user_id' => $owner->id, 'amount' => 100]],
    ])->assertUnprocessable()->assertJsonValidationErrors('distributions');
});

it('does not retire a custodian while reimbursement obligations remain', function () {
    [$account, $owner, $member] = custodianAccount();
    $headers = custodianHeaders($owner);
    $id = $this->withHeaders($headers)->postJson("/api/accounts/{$account->id}/custodians", ['name' => 'Nu'])->json('data.id');
    foreach ([[$id, $member->id, -25], [$member->id, $id, 25]] as [$userId, $relatedId, $amount]) {
        $account->memberLedgerEntries()->create([
            'user_id' => $userId, 'related_user_id' => $relatedId,
            'type'    => AccountMemberLedgerEntryType::SettlementCorrection,
            'amount'  => $amount, 'description' => 'Pending reimbursement', 'occurred_at' => now(),
        ]);
    }
    $this->withHeaders($headers)->deleteJson("/api/accounts/{$account->id}/custodians/{$id}", ['distributions' => []])
        ->assertUnprocessable()->assertJsonValidationErrors('custodian');
});

it('validates precision and duplicate source identifiers', function (array $payload) {
    [$account, $owner] = custodianAccount();
    $this->withHeaders(custodianHeaders($owner))->postJson("/api/accounts/{$account->id}/custodians", ['name' => 'Nu', ...$payload])->assertUnprocessable();
    expect($account->virtualCustodians()->count())->toBe(0);
})->with([
    [['opening_balance' => -0.01]],
    [['opening_balance' => 1.001]],
    [['transfers' => [['user_id' => 1, 'amount' => 1], ['user_id' => 1, 'amount' => 1]]]],
]);

it('moves existing virtual custody to a new custodian without increasing the balance', function () {
    [$account, $owner] = custodianAccount();
    $headers = custodianHeaders($owner);
    $id = $this->withHeaders($headers)->postJson("/api/accounts/{$account->id}/custodians", [
        'name' => 'Mercadopago', 'transfers' => [['user_id' => $owner->id, 'amount' => 100]],
    ])->assertCreated()->json('data.id');
    $this->withHeaders($headers)->postJson("/api/accounts/{$account->id}/custodians", [
        'name' => 'Nu', 'transfers' => [['user_id' => $id, 'amount' => 100]],
    ])->assertCreated()->assertJsonPath('data.custody_amount', 100.0);
    $this->withHeaders($headers)->deleteJson("/api/accounts/{$account->id}/custodians/{$id}", ['distributions' => []])->assertOk();
    $this->withHeaders($headers)->postJson("/api/accounts/{$account->id}/custodians", [
        'name' => 'Otra', 'transfers' => [['user_id' => $id, 'amount' => 0]],
    ])->assertUnprocessable();
    expect($account->fresh()->balance)->toBe(10000.0);
});

it('rejects creation in personal credit and virtual accounts', function (string $kind) {
    [$account, $owner] = custodianAccount();
    if ('personal' === $kind) {
        $account->users()->sync([$owner->id => ['percentage' => 100]]);
    }
    if ('credit' === $kind) {
        $account->update(['credit_card' => true]);
    }
    if ('virtual' === $kind) {
        $account->update(['virtual' => true]);
    }
    $this->withHeaders(custodianHeaders($owner))->postJson("/api/accounts/{$account->id}/custodians", ['name' => 'Nu'])->assertUnprocessable();
    expect($account->virtualCustodians()->count())->toBe(0);
})->with(['personal', 'credit', 'virtual']);

it('reserves outgoing custody reimbursements even when net settlements cancel', function () {
    [$account, $owner, $member] = custodianAccount();
    foreach ([[$owner, $member], [$member, $owner]] as [$debtor, $creditor]) {
        $transaction = Transaction::factory()->outcome()->completed()->create([
            'account_id'   => $account->id,
            'user_id'      => $owner->id,
            'amount'       => 1000,
            'scheduled_at' => now(),
        ]);
        foreach ([[$debtor->id, $creditor->id, -1000], [$creditor->id, $debtor->id, 1000]] as [$userId, $relatedId, $amount]) {
            $account->memberLedgerEntries()->create([
                'transaction_id'  => $transaction->id,
                'user_id'         => $userId,
                'related_user_id' => $relatedId,
                'type'            => AccountMemberLedgerEntryType::CustodyReimbursementDue,
                'amount'          => $amount,
                'description'     => 'Pending custody reimbursement',
                'occurred_at'     => now(),
            ]);
        }
    }
    $summary = app(BuildAccountMemberSummary::class)->execute($account);
    expect(collect($summary['settlements_by_user'])->firstWhere('user_id', $owner->id)['amount'])->toBe(0.0);
    $headers = custodianHeaders($owner);
    $this->withHeaders($headers)->postJson("/api/accounts/{$account->id}/custodians", [
        'name' => 'Nu', 'transfers' => [['user_id' => $owner->id, 'amount' => 9000.01]],
    ])->assertUnprocessable()->assertJsonValidationErrors('transfers');
    expect($account->virtualCustodians()->count())->toBe(0);
    $this->withHeaders($headers)->postJson("/api/accounts/{$account->id}/custodians", [
        'name' => 'Nu', 'transfers' => [['user_id' => $owner->id, 'amount' => 9000]],
    ])->assertCreated()->assertJsonPath('data.custody_amount', 9000.0);
});
