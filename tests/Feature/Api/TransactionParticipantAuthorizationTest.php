<?php

use App\Enums\TransactionPaymentSource;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Auth\JwtTokenService;

function participantScenario(): array
{
    $owner = User::factory()->create();
    $former = User::factory()->create();
    $outsider = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $owner->id]);
    $account->users()->attach($owner->id, ['percentage' => 50]);
    $account->users()->attach($former->id, ['percentage' => 50]);
    $transaction = Transaction::factory()->outcome()->completed()->create([
        'account_id' => $account->id, 'user_id' => $owner->id, 'paid_by_user_id' => $former->id,
        'payment_source' => TransactionPaymentSource::MemberOutOfPocket,
    ]);
    foreach ([$owner, $former] as $member) {
        $transaction->allocations()->create(['user_id' => $member->id, 'percentage' => 50, 'amount' => 50]);
    }
    $account->users()->detach($former->id);
    $payload = [
        'account_id' => $account->id, 'type' => 'outcome', 'status' => 'completed',
        'concept' => 'Historical expense edited', 'amount' => 100, 'scheduled_at' => '2026-10-10',
        'paid_by_user_id' => $former->id, 'payment_source' => 'member_out_of_pocket',
        'split_between_users' => true,
        'user_payments' => [['user_id' => $owner->id, 'percentage' => 50], ['user_id' => $former->id, 'percentage' => 50]],
    ];
    return [$owner, $former, $outsider, $account, $transaction, $payload];
}

function participantHeaders(User $user): array
{
    return ['Authorization' => 'Bearer '.app(JwtTokenService::class)->generate($user)['token'], 'Accept' => 'application/json'];
}

it('allows an author to retain historical participants after membership changes', function (bool $nested) {
    [$owner, $former, , $account, $transaction, $payload] = participantScenario();
    $path = $nested ? "/api/accounts/{$account->id}/transactions/{$transaction->id}" : "/api/transactions/{$transaction->id}";
    $this->withHeaders(participantHeaders($owner))->putJson($path, $payload)->assertOk();
    expect($transaction->fresh()->paid_by_user_id)->toBe($former->id)
        ->and($transaction->fresh()->payment_source)->toBe(TransactionPaymentSource::MemberOutOfPocket)
        ->and($transaction->allocations()->where('user_id', $former->id)->value('percentage'))->toEqual(50);
})->with([true, false]);

it('rejects new nonmember participants and changing a historical participant role', function (bool $nested, string $change) {
    [$owner, $former, $outsider, $account, $transaction, $payload] = participantScenario();
    $path = $nested ? "/api/accounts/{$account->id}/transactions/{$transaction->id}" : "/api/transactions/{$transaction->id}";
    if ($change === 'payer') {
        $payload['paid_by_user_id'] = $outsider->id;
    }
    if ($change === 'allocation') {
        $payload['user_payments'][1]['user_id'] = $outsider->id;
    }
    if ($change === 'custodian') {
        $payload['custodian_user_id'] = $former->id;
    }
    $this->withHeaders(participantHeaders($owner))->putJson($path, $payload)->assertNotFound();
    expect($transaction->fresh()->concept)->not->toBe('Historical expense edited');
})->with([true, false])->with(['payer', 'allocation', 'custodian']);

it('does not carry historical participant authorization to a different account', function () {
    [$owner, , , , $transaction, $payload] = participantScenario();
    $destination = Account::factory()->create(['user_id' => $owner->id]);
    $destination->users()->attach($owner->id, ['percentage' => 100]);
    $payload['account_id'] = $destination->id;
    $this->withHeaders(participantHeaders($owner))->putJson("/api/transactions/{$transaction->id}", $payload)->assertNotFound();
});

it('does not let a departed author retain write access through historical participants', function () {
    [$owner, $former, , $account, $transaction, $payload] = participantScenario();
    $transaction->update(['user_id' => $former->id]);
    $this->withHeaders(participantHeaders($former))->putJson("/api/accounts/{$account->id}/transactions/{$transaction->id}", $payload)->assertForbidden();
});

it('rejects client supplied financial classification on both transaction APIs', function (bool $nested) {
    [$owner, , , $account, , ] = participantScenario();
    $payload = ['account_id' => $account->id, 'type' => 'outcome', 'status' => 'completed', 'concept' => 'Forged mode', 'amount' => 10, 'scheduled_at' => '2026-10-10', 'uses_shared_account_rules' => true];
    $path = $nested ? "/api/accounts/{$account->id}/transactions" : '/api/transactions';
    $this->withHeaders(participantHeaders($owner))->postJson($path, $payload)->assertUnprocessable()->assertJsonValidationErrors('uses_shared_account_rules');
})->with([true, false]);
