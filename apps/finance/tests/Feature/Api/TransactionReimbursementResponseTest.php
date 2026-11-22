<?php

declare(strict_types=1);

use FinSi\FinanceDomain\Models\Account;
use FinSi\FinanceDomain\Models\User;

it('returns immediate reimbursement indicators on create edit and show for the authenticated user', function (bool $nested, bool $authorPays): void {
    $author = User::factory()->create();
    $member = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $author->id, 'balance' => '0.00']);
    $account->users()->sync([
        $author->id => ['percentage' => '50.00'],
        $member->id => ['percentage' => '50.00'],
    ]);
    $this->withHeaders(['Authorization' => 'Bearer '.\Tests\Support\AcceptedUserToken::generate($author)['token']]);
    $base = $nested ? "/api/accounts/{$account->id}/transactions" : '/api/transactions';
    $payload = [
        'account_id' => $account->id,
        'type' => 'outcome',
        'status' => 'completed',
        'concept' => 'Shared lunch',
        'amount' => '100.00',
        'scheduled_at' => '2026-10-10',
        'paid_by_user_id' => $authorPays ? $author->id : $member->id,
        'payment_source' => 'member_out_of_pocket',
        'split_between_users' => true,
        'user_payments' => [
            ['user_id' => $author->id, 'percentage' => '50.00'],
            ['user_id' => $member->id, 'percentage' => '50.00'],
        ],
    ];
    $created = $this->postJson($base, $payload)->assertCreated();
    $id = $created->json('data.id');
    $created->assertJsonPath('data.current_user_pending_reimbursement_amount', $authorPays ? '0.00' : '50.00')
        ->assertJsonPath('data.current_user_receivable_reimbursement_amount', $authorPays ? '50.00' : '0.00');
    $this->getJson($base."/{$id}")->assertOk()
        ->assertJsonPath('data.current_user_pending_reimbursement_amount', $authorPays ? '0.00' : '50.00')
        ->assertJsonPath('data.current_user_receivable_reimbursement_amount', $authorPays ? '50.00' : '0.00');
    $payload['amount'] = '200.00';
    $this->putJson($base."/{$id}", $payload)->assertOk()
        ->assertJsonPath('data.current_user_pending_reimbursement_amount', $authorPays ? '0.00' : '100.00')
        ->assertJsonPath('data.current_user_receivable_reimbursement_amount', $authorPays ? '100.00' : '0.00');
    $this->getJson($base)->assertOk()
        ->assertJsonPath('data.0.current_user_pending_reimbursement_amount', $authorPays ? '0.00' : '100.00')
        ->assertJsonPath('data.0.current_user_receivable_reimbursement_amount', $authorPays ? '100.00' : '0.00');
})->with([[true, true], [true, false], [false, true], [false, false]]);
