<?php

use App\Models\Account;
use App\Models\AccountInvite;
use App\Models\User;
use App\Services\Auth\JwtTokenService;

beforeEach(function (): void {
    $this->owner = User::factory()->create(['name' => 'Account Owner']);
    $this->member = User::factory()->create(['name' => 'Shared Member']);
    $this->account = Account::factory()->create(['user_id' => $this->owner->id]);
    $this->account->users()->sync([
        $this->owner->id => ['percentage' => 65],
        $this->member->id => ['percentage' => 35],
    ]);
    $this->invite = AccountInvite::withoutEvents(fn (): AccountInvite => AccountInvite::factory()->accepted()->create([
        'account_id' => $this->account->id,
        'user_id' => $this->owner->id,
        'email' => $this->member->email,
        'percentage' => 35,
    ]));
    $token = app(JwtTokenService::class)->generate($this->member)['token'];
    $this->withHeaders(['Authorization' => 'Bearer '.$token]);
});

it('lets members read participants with correct identities and allocation percentages', function (): void {
    $this->getJson("/api/accounts/{$this->account->id}/users?per_page=1")
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.id', $this->owner->id)
        ->assertJsonPath('data.0.pivot.percentage', 65);

    $this->getJson("/api/accounts/{$this->account->id}/users?search=Shared")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->member->id)
        ->assertJsonPath('data.0.pivot.percentage', 35);

    $this->getJson("/api/accounts/{$this->account->id}/users/{$this->owner->id}")
        ->assertOk()->assertJsonPath('data.id', $this->owner->id);
});

it('lets members read account invitations and received invitation history', function (): void {
    $this->getJson("/api/accounts/{$this->account->id}/invites")
        ->assertOk()->assertJsonPath('data.0.id', $this->invite->id);
    $this->getJson("/api/accounts/{$this->account->id}/invites/{$this->invite->id}")
        ->assertOk()->assertJsonPath('data.id', $this->invite->id);
    $this->getJson('/api/account-invites')
        ->assertOk()->assertJsonPath('data.0.status', 'accepted');
});

it('keeps participant and invitation administration restricted to the owner', function (): void {
    $this->putJson("/api/accounts/{$this->account->id}/users/{$this->owner->id}", ['percentage' => 50])->assertForbidden();
    $this->deleteJson("/api/accounts/{$this->account->id}/users/{$this->owner->id}")->assertForbidden();
    $this->putJson("/api/accounts/{$this->account->id}/invites/{$this->invite->id}", ['email' => $this->member->email, 'percentage' => 35])->assertForbidden();
    $this->deleteJson("/api/accounts/{$this->account->id}/invites/{$this->invite->id}")->assertForbidden();
});

it('rejects outsiders and invitations belonging to another account', function (): void {
    $foreignInvite = AccountInvite::withoutEvents(fn (): AccountInvite => AccountInvite::factory()->create());
    $this->getJson("/api/accounts/{$this->account->id}/invites/{$foreignInvite->id}")->assertNotFound();
    $outsider = User::factory()->create();
    $token = app(JwtTokenService::class)->generate($outsider)['token'];
    $this->withHeaders(['Authorization' => 'Bearer '.$token]);
    $this->getJson("/api/accounts/{$this->account->id}/users")->assertForbidden();
    $this->getJson("/api/accounts/{$this->account->id}/invites")->assertForbidden();
});
