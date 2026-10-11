<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\PersonalMoneyLink;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Auth\JwtTokenService;

function personalMoneyApiHeaders(User $user): array
{
    return ['Authorization' => 'Bearer ' . app(JwtTokenService::class)->generate($user)['token']];
}

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 10, 11)->setTime(12, 0));
    $this->user = User::factory()->create();
    $this->other = User::factory()->create();
    $this->personal = Account::factory()->create(['user_id' => $this->user->id, 'virtual' => false, 'credit_card' => false]);
    $this->personal->users()->attach($this->user->id);
    $this->shared = Account::factory()->create(['user_id' => $this->user->id, 'virtual' => false, 'credit_card' => false, 'balance' => 0]);
    $this->shared->users()->sync([$this->user->id => ['percentage' => 50], $this->other->id => ['percentage' => 50]]);
    $this->settingsPayload = ['account_id' => $this->personal->id, 'started_at' => '2026-10-01', 'opening_balance' => 1000, 'enabled' => true];
});

it('requires authentication and leaves the feature disabled by default', function (): void {
    $this->getJson('/api/personal-money')->assertUnauthorized();
    $this->withHeaders(personalMoneyApiHeaders($this->user))->getJson('/api/personal-money')->assertOk()
        ->assertJsonPath('data.settings', null)->assertJsonPath('data.available_balance', null)
        ->assertJsonPath('data.history', [])->assertJsonCount(1, 'data.accounts');
});

it('validates private ownership dates precision and pagination', function (): void {
    $this->withHeaders(personalMoneyApiHeaders($this->user));
    $this->putJson('/api/personal-money/settings', array_replace($this->settingsPayload, ['account_id' => $this->shared->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('account_id');
    $this->putJson('/api/personal-money/settings', array_replace($this->settingsPayload, ['started_at' => '2026-10-12']))
        ->assertUnprocessable()->assertJsonValidationErrors('started_at');
    $this->putJson('/api/personal-money/settings', array_replace($this->settingsPayload, ['opening_balance' => 1.234]))
        ->assertUnprocessable()->assertJsonValidationErrors('opening_balance');
    $this->getJson('/api/personal-money?per_page=101&resolution=private')->assertUnprocessable();
    $this->putJson('/api/personal-money/settings', $this->settingsPayload)->assertOk()
        ->assertJsonPath('data.available_balance', 1000.0);
    $this->withHeaders(personalMoneyApiHeaders($this->other))->getJson('/api/personal-money')->assertOk()
        ->assertJsonPath('data.settings', null)->assertJsonPath('data.accounts', [])->assertJsonPath('data.history', []);
});

it('saves the cash source atomically through both transaction endpoints', function (bool $nested): void {
    $this->withHeaders(personalMoneyApiHeaders($this->user))
        ->putJson('/api/personal-money/settings', $this->settingsPayload)->assertOk();
    $url = $nested ? "/api/accounts/{$this->shared->id}/transactions" : '/api/transactions';
    $response = $this->postJson($url, [
        'account_id'        => $this->shared->id, 'type' => 'income', 'status' => 'completed',
        'concept'           => 'Contribution', 'amount' => 200, 'scheduled_at' => '2026-10-05',
        'custodian_user_id' => $this->other->id, 'cash_source' => 'personal',
    ])->assertCreated();
    $transactionId = $response->json('data.id');
    expect(PersonalMoneyLink::query()->where('transaction_id', $transactionId)->count())->toBe(1);
    $this->getJson('/api/personal-money')->assertOk()->assertJsonPath('data.available_balance', 800.0);
    $this->withHeaders(personalMoneyApiHeaders($this->other))
        ->getJson("/api/accounts/{$this->shared->id}/transactions/{$transactionId}")->assertOk()
        ->assertJsonMissingPath('data.cash_source')->assertJsonMissingPath('data.personal_money_links')
        ->assertJsonMissingPath('data.account.opening_balance');
})->with([true, false]);

it('rolls back the transaction when the private source is not eligible', function (): void {
    $this->withHeaders(personalMoneyApiHeaders($this->user))
        ->postJson("/api/accounts/{$this->shared->id}/transactions", [
            'type'   => 'income', 'status' => 'completed', 'concept' => 'Invalid source',
            'amount' => 200, 'scheduled_at' => '2026-10-05', 'cash_source' => 'personal',
        ])->assertUnprocessable();
    expect(Transaction::withoutGlobalScopes()->where('concept', 'Invalid source')->exists())->toBeFalse();
});

it('does not let another member connect a payment made by the payer', function (): void {
    $this->withHeaders(personalMoneyApiHeaders($this->user))->putJson('/api/personal-money/settings', $this->settingsPayload)->assertOk();
    $transactionId = $this->withHeaders(personalMoneyApiHeaders($this->other))
        ->postJson("/api/accounts/{$this->shared->id}/transactions", [
            'type'            => 'outcome', 'status' => 'completed', 'concept' => 'Recorded by partner',
            'amount'          => 100, 'scheduled_at' => '2026-10-05',
            'paid_by_user_id' => $this->user->id, 'payment_source' => 'member_out_of_pocket',
        ])->assertCreated()->json('data.id');
    $this->getJson('/api/personal-money')->assertJsonPath('data.settings', null);
    $this->withHeaders(personalMoneyApiHeaders($this->user))->getJson('/api/personal-money')
        ->assertJsonPath('data.pending_count', 1)->assertJsonPath('data.available_balance', 1000.0);
    $this->putJson('/api/personal-money/links', [
        'source_type' => 'transaction', 'source_id' => $transactionId, 'resolution' => 'personal',
    ])->assertOk()->assertJsonPath('data.available_balance', 900.0);
    $this->withHeaders(personalMoneyApiHeaders($this->other))->getJson("/api/accounts/{$this->shared->id}/transactions/{$transactionId}")
        ->assertOk()->assertJsonMissingPath('data.cash_source');
});

it('rejects inaccessible and automatic sources and invalid source types', function (): void {
    $this->withHeaders(personalMoneyApiHeaders($this->user))->putJson('/api/personal-money/settings', $this->settingsPayload)->assertOk();
    $transaction = Transaction::factory()->income()->create([
        'account_id'                => $this->personal->id, 'user_id' => $this->user->id,
        'uses_shared_account_rules' => false, 'scheduled_at' => '2026-10-05',
    ]);
    $payload = ['source_type' => 'transaction', 'source_id' => $transaction->id, 'resolution' => 'personal'];
    $this->putJson('/api/personal-money/links', $payload)->assertUnprocessable();
    $this->putJson('/api/personal-money/links', array_replace($payload, ['source_type' => 'arbitrary']))->assertUnprocessable();
    $outsider = User::factory()->create();
    $outsideAccount = Account::factory()->create(['user_id' => $outsider->id]);
    $outsideAccount->users()->attach($outsider->id);
    $outside = Transaction::factory()->income()->create(['account_id' => $outsideAccount->id, 'user_id' => $outsider->id, 'scheduled_at' => '2026-10-05']);
    $this->putJson('/api/personal-money/links', array_replace($payload, ['source_id' => $outside->id]))->assertNotFound();
});

it('activates from now without a start date and validates reset operation keys', function (): void {
    $this->withHeaders(personalMoneyApiHeaders($this->user));
    $payload = ['account_id' => $this->personal->id, 'opening_balance' => 10000, 'enabled' => true, 'start_mode' => 'now'];
    $this->putJson('/api/personal-money/settings', $payload)->assertUnprocessable()->assertJsonValidationErrors('operation_key');
    $payload['operation_key'] = (string) Illuminate\Support\Str::uuid();
    $response = $this->putJson('/api/personal-money/settings', $payload)->assertOk()
        ->assertJsonPath('data.available_balance', 10000.0)->assertJsonPath('data.settings.start_mode', 'now')
        ->assertJsonPath('data.settings.started_at', '2026-10-11')->assertJsonPath('data.history', []);
    expect($response->json('data.settings.activated_at'))->not->toBeNull();
    expect($response->json('data.settings.transaction_cutoff_id'))->toBeInt();
    $this->putJson('/api/personal-money/settings', array_replace($payload, ['opening_balance' => 9000]))
        ->assertUnprocessable()->assertJsonValidationErrors('operation_key');
    $this->withHeaders(personalMoneyApiHeaders($this->other))->getJson('/api/personal-money')->assertOk()
        ->assertJsonPath('data.settings', null);
});

it('defaults current own shared cash payments through both endpoints', function (bool $nested): void {
    $this->withHeaders(personalMoneyApiHeaders($this->user))->putJson('/api/personal-money/settings', [
        'account_id' => $this->personal->id, 'opening_balance' => 10000, 'enabled' => true,
        'start_mode' => 'now', 'operation_key' => (string) Illuminate\Support\Str::uuid(),
    ])->assertOk();
    $url = $nested ? "/api/accounts/{$this->shared->id}/transactions" : '/api/transactions';
    $this->postJson($url, [
        'account_id'      => $this->shared->id, 'type' => 'outcome', 'status' => 'completed',
        'concept'         => 'Current payment', 'amount' => 200, 'scheduled_at' => '2026-10-11',
        'paid_by_user_id' => $this->user->id, 'payment_source' => 'member_out_of_pocket',
    ])->assertCreated()->assertJsonMissingPath('data.cash_source');
    $this->getJson('/api/personal-money')->assertOk()->assertJsonPath('data.available_balance', 9800.0)->assertJsonPath('data.pending_count', 0);
})->with([true, false]);

it('keeps the local activation day without using the client clock for the actual cut', function (): void {
    $this->withHeaders(personalMoneyApiHeaders($this->user))->putJson('/api/personal-money/settings', [
        'account_id' => $this->personal->id, 'opening_balance' => 10000, 'enabled' => true,
        'start_mode' => 'now', 'started_at' => '2026-10-10', 'operation_key' => (string) Illuminate\Support\Str::uuid(),
    ])->assertOk()->assertJsonPath('data.settings.started_at', '2026-10-10')
        ->assertJsonPath('data.settings.activated_at', now()->toIso8601String())->assertJsonPath('data.available_balance', 10000.0);
});
