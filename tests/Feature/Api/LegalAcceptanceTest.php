<?php

declare(strict_types=1);

use App\Dto\Legal\LegalAcceptanceData;
use App\Models\LegalAcceptance;
use App\Models\User;
use App\Services\Auth\JwtTokenService;
use App\Services\Legal\GetLegalAcceptanceState;
use App\Services\Legal\GetLegalDocuments;
use App\Services\Legal\RecordLegalAcceptance;
use Illuminate\Support\Facades\Notification;

function legalAcceptancePayload(): array
{
    $documents = app(GetLegalDocuments::class)->execute()['documents'];

    return [
        'terms_accepted'          => true,
        'privacy_policy_accepted' => true,
        'is_adult'                => true,
        'terms_hash'              => $documents['terms']['hash'],
        'privacy_hash'            => $documents['privacy']['hash'],
    ];
}

function legalRawHeaders(User $user): array
{
    return ['Authorization' => 'Bearer ' . app(JwtTokenService::class)->generate($user)['token']];
}

it('publishes deterministic documents whose identity, content and version affect the hash', function (): void {
    $service = app(GetLegalDocuments::class);
    $first = $service->execute();
    expect($first['available'])->toBeTrue()
        ->and($service->execute())->toBe($first);
    config(['legal.provider.name' => 'New Provider']);
    $second = $service->execute();
    expect($second['documents']['terms']['hash'])->not->toBe($first['documents']['terms']['hash'])
        ->and($second['documents']['privacy']['hash'])->not->toBe($first['documents']['privacy']['hash']);
    config(['legal.terms_version' => 'next']);
    expect($service->execute()['documents']['terms']['hash'])->not->toBe($second['documents']['terms']['hash']);
    $this->getJson('/api/legal-documents')->assertOk()->assertJsonPath('data.available', true);
});

it('requires real acceptance for existing tokens while keeping profile and legal endpoints available', function (): void {
    $user = User::factory()->create();
    $headers = legalRawHeaders($user);
    $this->withHeaders($headers)->getJson('/api/accounts')->assertStatus(428)->assertJsonPath('code', 'legal_acceptance_required');
    $this->getJson('/api/profile')->assertOk();
    $this->getJson('/api/legal-acceptance')->assertOk()->assertJsonPath('data.required', true);
    $this->postJson('/api/legal-acceptance', legalAcceptancePayload())->assertOk()->assertJsonPath('data.required', false);
    $this->getJson('/api/accounts')->assertOk();
    $this->deleteJson('/api/auth/logout')->assertOk();
});

it('stores the exact snapshot once and preserves the original acceptance time on retries', function (): void {
    $user = User::factory()->create();
    $payload = legalAcceptancePayload();
    $data = new LegalAcceptanceData($payload['terms_hash'], $payload['privacy_hash'], true, true, true);
    $record = app(RecordLegalAcceptance::class)->execute($user, $data);
    $time = $record->accepted_at;
    $this->travel(1)->hour();
    $retry = app(RecordLegalAcceptance::class)->execute($user, $data);
    expect($retry->id)->toBe($record->id)
        ->and($retry->accepted_at->equalTo($time))->toBeTrue()
        ->and($retry->snapshot)->toBe(app(GetLegalDocuments::class)->execute())
        ->and($retry->financial_data_consent)->toBeTrue()
        ->and($retry->is_adult)->toBeTrue();
    $this->assertDatabaseCount('legal_acceptances', 1);
    config(['legal.privacy_version' => 'next']);
    expect(app(GetLegalAcceptanceState::class)->execute($user)['required'])->toBeTrue();
});

it('rejects stale documents, missing declarations and user impersonation', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $this->withHeaders(legalRawHeaders($user));
    $this->postJson('/api/legal-acceptance', [...legalAcceptancePayload(), 'terms_hash' => str_repeat('0', 64)])->assertStatus(409);
    foreach (['is_adult', 'terms_accepted', 'privacy_policy_accepted'] as $field) {
        $this->postJson('/api/legal-acceptance', [...legalAcceptancePayload(), $field => false])->assertUnprocessable()->assertJsonValidationErrors($field);
    }
    $this->postJson('/api/legal-acceptance', [...legalAcceptancePayload(), 'user_id' => $other->id])->assertOk();
    expect(LegalAcceptance::query()->sole()->user_id)->toBe($user->id);
});

it('rejects service-level false declarations without recording evidence', function (): void {
    $user = User::factory()->create();
    $payload = legalAcceptancePayload();
    expect(static fn () => app(RecordLegalAcceptance::class)->execute($user, new LegalAcceptanceData($payload['terms_hash'], $payload['privacy_hash'], false, true, true)))
        ->toThrow(Illuminate\Validation\ValidationException::class);
    $this->assertDatabaseCount('legal_acceptances', 0);
});

it('fails closed for missing identity even when previous evidence exists', function (): void {
    $user = User::factory()->create();
    $this->withHeaders(legalRawHeaders($user))->postJson('/api/legal-acceptance', legalAcceptancePayload())->assertOk();
    $payload = legalAcceptancePayload();
    config(['legal.provider.rfc' => '']);
    $this->getJson('/api/legal-documents')->assertOk()->assertJsonPath('data.available', false);
    $this->getJson('/api/legal-acceptance')->assertOk()->assertJsonPath('data.required', true)->assertJsonPath('data.available', false);
    $this->postJson('/api/legal-acceptance', $payload)->assertStatus(503);
    $this->getJson('/api/accounts')->assertStatus(428);
});

it('registers atomically with the current receipt and sends no notification for a stale registration', function (): void {
    Notification::fake();
    $registration = [
        'name'     => 'Adult User', 'email' => 'adult@example.com',
        'password' => 'password123', 'password_confirmation' => 'password123',
        ...legalAcceptancePayload(),
    ];
    $this->postJson('/api/auth/register', [...$registration, 'terms_hash' => str_repeat('0', 64)])->assertStatus(409);
    $this->assertDatabaseMissing('users', ['email' => 'adult@example.com']);
    $this->assertDatabaseCount('legal_acceptances', 0);
    Notification::assertNothingSent();
    $this->postJson('/api/auth/register', $registration)->assertCreated();
    expect(app(GetLegalAcceptanceState::class)->execute(User::query()->where('email', 'adult@example.com')->sole())['required'])->toBeFalse();
    Notification::assertSentTo(User::query()->where('email', 'adult@example.com')->sole(), Illuminate\Auth\Notifications\VerifyEmail::class);
});

it('does not allow anonymous acceptance or disclose an authenticated acceptance state', function (): void {
    $this->getJson('/api/legal-acceptance')->assertUnauthorized();
    $this->postJson('/api/legal-acceptance', legalAcceptancePayload())->assertUnauthorized();
});
