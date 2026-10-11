<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\User;
use App\Services\Auth\JwtTokenService;

it('returns immediate reimbursement indicators on create edit and show for the authenticated user', function (bool $nested, bool $authorPays): void {
    $author = User::factory()->create();
    $member = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $author->id, 'balance' => 0]);
    $account->users()->sync([
        $author->id => ['percentage' => 50],
        $member->id => ['percentage' => 50],
    ]);
    $this->withHeaders(['Authorization' => 'Bearer '.app(JwtTokenService::class)->generate($author)['token']]);
    $base = $nested ? "/api/accounts/{$account->id}/transactions" : '/api/transactions';
    $payload = [
        'account_id' => $account->id,
        'type' => 'outcome',
        'status' => 'completed',
        'concept' => 'Shared lunch',
        'amount' => 100,
        'scheduled_at' => '2026-10-10',
        'paid_by_user_id' => $authorPays ? $author->id : $member->id,
        'payment_source' => 'member_out_of_pocket',
        'split_between_users' => true,
        'user_payments' => [
            ['user_id' => $author->id, 'percentage' => 50],
            ['user_id' => $member->id, 'percentage' => 50],
        ],
    ];
    $created = $this->postJson($base, $payload)->assertCreated();
    $id = $created->json('data.id');
    $created->assertJsonPath('data.current_user_pending_reimbursement_amount', $authorPays ? 0.0 : 50.0)
        ->assertJsonPath('data.current_user_receivable_reimbursement_amount', $authorPays ? 50.0 : 0.0);
    $this->getJson($base."/{$id}")->assertOk()
        ->assertJsonPath('data.current_user_pending_reimbursement_amount', $authorPays ? 0.0 : 50.0)
        ->assertJsonPath('data.current_user_receivable_reimbursement_amount', $authorPays ? 50.0 : 0.0);
    $payload['amount'] = 200;
    $this->putJson($base."/{$id}", $payload)->assertOk()
        ->assertJsonPath('data.current_user_pending_reimbursement_amount', $authorPays ? 0.0 : 100.0)
        ->assertJsonPath('data.current_user_receivable_reimbursement_amount', $authorPays ? 100.0 : 0.0);
    $this->getJson($base)->assertOk()
        ->assertJsonPath('data.0.current_user_pending_reimbursement_amount', $authorPays ? 0.0 : 100.0)
        ->assertJsonPath('data.0.current_user_receivable_reimbursement_amount', $authorPays ? 100.0 : 0.0);
})->with([[true, true], [true, false], [false, true], [false, false]]);
