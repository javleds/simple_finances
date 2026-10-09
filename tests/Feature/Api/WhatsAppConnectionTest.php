<?php

use App\Models\User;
use App\Models\WhatsAppConnection;
use App\Models\WhatsAppVerificationCode;
use App\Services\Auth\JwtTokenService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'services.whatsapp.phone_number_id' => '123456',
        'services.whatsapp.access_token' => 'access-secret',
        'services.whatsapp.graph_version' => 'v25.0',
        'services.whatsapp.auth_template_name' => 'verification',
        'services.whatsapp.auth_template_language' => 'es_MX',
    ]);
    Http::preventStrayRequests();
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]])]);
    $this->user = User::factory()->create();
    $this->headers = whatsappHeaders($this->user);
});

function whatsappHeaders(User $user): array
{
    return ['Authorization' => 'Bearer '.app(JwtTokenService::class)->generate($user)['token']];
}

function pendingWhatsappCode(User $user, string $phone = '+525512345678', string $code = '001234'): WhatsAppVerificationCode
{
    return WhatsAppVerificationCode::query()->create([
        'user_id' => $user->id,
        'phone_number' => $phone,
        'code_hash' => Hash::make($code),
        'status' => 'sent',
        'expires_at' => now()->addHours(24),
    ]);
}

it('requires authentication for every connection operation', function (): void {
    $this->getJson('/api/whatsapp-connection')->assertUnauthorized();
    $this->postJson('/api/whatsapp-verification-codes', ['phone_number' => '+525512345678'])->assertUnauthorized();
    $this->postJson('/api/whatsapp-connection', ['code' => '001234'])->assertUnauthorized();
    $this->deleteJson('/api/whatsapp-connection')->assertUnauthorized();
    Http::assertNothingSent();
});

it('starts unlinked and sends matching template parameters without exposing the code', function (): void {
    $this->freezeSecond();
    $this->getJson('/api/whatsapp-connection', $this->headers)->assertOk()->assertJsonPath('data.status', 'unlinked');
    $response = $this->postJson('/api/whatsapp-verification-codes', ['phone_number' => '+525512345678'], $this->headers)
        ->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.phone_number', '+525512345678');
    $request = Http::recorded()->sole()[0];
    $code = $request['template']['components'][0]['parameters'][0]['text'];
    expect($code)->toMatch('/^[0-9]{6}$/');
    expect($request['template']['components'][1]['parameters'][0]['text'])->toBe($code);
    expect($request['to'])->toBe('525512345678');
    expect($request['template']['name'])->toBe('verification');
    expect($request['template']['language']['code'])->toBe('es_MX');
    expect($request->hasHeader('Authorization', 'Bearer access-secret'))->toBeTrue();
    $stored = WhatsAppVerificationCode::query()->sole();
    expect(Hash::check($code, $stored->code_hash))->toBeTrue();
    expect($stored->expires_at->equalTo(now()->addHours(24)))->toBeTrue();
    expect($stored->toArray())->not->toHaveKey('code_hash');
    expect($response->json('data'))->not->toHaveKeys(['code', 'code_hash']);
    $this->getJson('/api/whatsapp-connection', $this->headers)->assertExactJson($response->json());
});

it('validates Mexican phones before sending', function (mixed $phone): void {
    $this->postJson('/api/whatsapp-verification-codes', ['phone_number' => $phone], $this->headers)
        ->assertUnprocessable()->assertJsonValidationErrors('phone_number');
    Http::assertNothingSent();
    $this->assertDatabaseCount('whatsapp_verification_codes', 0);
})->with(['missing' => [null], 'array' => [['+525512345678']], 'foreign' => ['+15551234567'], 'short' => ['+52551234567'], 'long' => ['+5255123456789'], 'spaces' => ['+52 5512345678']]);

it('confirms leading zeros and prevents reuse after consumption', function (): void {
    $code = pendingWhatsappCode($this->user);
    $this->postJson('/api/whatsapp-connection', ['code' => '001234'], $this->headers)
        ->assertOk()->assertJsonPath('data.status', 'linked')->assertJsonPath('data.phone_number', $code->phone_number);
    expect($code->fresh()->status)->toBe('consumed');
    $this->postJson('/api/whatsapp-connection', ['code' => '001234'], $this->headers)->assertConflict();
    $this->assertDatabaseCount('whatsapp_connections', 1);
});

it('does not allow another user to confirm the pending code', function (): void {
    $code = pendingWhatsappCode($this->user);
    $other = User::factory()->create();
    $this->postJson('/api/whatsapp-connection', ['code' => '001234'], whatsappHeaders($other))->assertUnprocessable();
    expect($code->fresh()->attempts)->toBe(0);
    $this->assertDatabaseCount('whatsapp_connections', 0);
});

it('rejects a code exactly at expiration and hides the pending state', function (): void {
    $code = pendingWhatsappCode($this->user);
    $this->travelTo($code->expires_at);
    $this->postJson('/api/whatsapp-connection', ['code' => '001234'], $this->headers)->assertUnprocessable();
    $this->getJson('/api/whatsapp-connection', $this->headers)->assertJsonPath('data.status', 'unlinked');
    $this->assertDatabaseCount('whatsapp_connections', 0);
});

it('persists five wrong attempts and rejects the correct code afterward', function (): void {
    $code = pendingWhatsappCode($this->user);
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson('/api/whatsapp-connection', ['code' => '999999'], $this->headers)->assertUnprocessable();
    }
    expect($code->fresh()->attempts)->toBe(5);
    $this->postJson('/api/whatsapp-connection', ['code' => '001234'], $this->headers)->assertUnprocessable();
    expect($code->fresh()->attempts)->toBe(5);
    $this->getJson('/api/whatsapp-connection', $this->headers)->assertJsonPath('data.status', 'unlinked');
});

it('invalidates the previous code when resending after the cooldown', function (): void {
    $this->postJson('/api/whatsapp-verification-codes', ['phone_number' => '+525512345678'], $this->headers)->assertCreated();
    $old = WhatsAppVerificationCode::query()->sole();
    $this->travel(61)->seconds();
    $this->postJson('/api/whatsapp-verification-codes', ['phone_number' => '+525587654321'], $this->headers)->assertCreated();
    expect($old->fresh()->status)->toBe('invalidated');
    $new = WhatsAppVerificationCode::query()->latest('id')->firstOrFail();
    expect($new->status)->toBe('sent');
    $sent = Http::recorded()->last()[0]['template']['components'][0]['parameters'][0]['text'];
    $this->postJson('/api/whatsapp-connection', ['code' => $sent], $this->headers)->assertOk()
        ->assertJsonPath('data.phone_number', '+525587654321');
});

it('never leaves a usable code when Meta rejects the request', function (array $body, int $status): void {
    Http::swap(new \Illuminate\Http\Client\Factory());
    Http::fake(['graph.facebook.com/*' => Http::response($body, $status)]);
    $this->postJson('/api/whatsapp-verification-codes', ['phone_number' => '+525512345678'], $this->headers)->assertStatus(502);
    expect(WhatsAppVerificationCode::query()->sole()->status)->toBe('failed');
    $this->getJson('/api/whatsapp-connection', $this->headers)->assertJsonPath('data.status', 'unlinked');
})->with(['error' => [[], 400], 'missing acceptance' => [[], 200]]);

it('handles network failures without a usable code', function (): void {
    Http::fake(['*' => Http::failedConnection()]);
    $this->postJson('/api/whatsapp-verification-codes', ['phone_number' => '+525512345678'], $this->headers)->assertStatus(502);
    expect(WhatsAppVerificationCode::query()->sole()->status)->toBe('failed');
});

it('rejects incomplete sending configuration', function (): void {
    config(['services.whatsapp.access_token' => '']);
    $this->postJson('/api/whatsapp-verification-codes', ['phone_number' => '+525512345678'], $this->headers)->assertStatus(503);
    expect(WhatsAppVerificationCode::query()->sole()->status)->toBe('failed');
    Http::assertNothingSent();
});

it('rejects already linked numbers both before sending and during confirmation', function (): void {
    $other = User::factory()->create();
    WhatsAppConnection::query()->create(['user_id' => $other->id, 'phone_number' => '+525512345678', 'verified_at' => now()]);
    $this->postJson('/api/whatsapp-verification-codes', ['phone_number' => '+525512345678'], $this->headers)->assertConflict();
    pendingWhatsappCode($this->user);
    $this->postJson('/api/whatsapp-connection', ['code' => '001234'], $this->headers)->assertConflict();
    $this->assertDatabaseCount('whatsapp_connections', 1);
    Http::assertNothingSent();
});

it('disconnects only the current user and invalidates pending codes idempotently', function (): void {
    $other = User::factory()->create();
    foreach ([$this->user, $other] as $user) {
        WhatsAppConnection::query()->create(['user_id' => $user->id, 'phone_number' => '+52550000000'.$user->id, 'verified_at' => now()]);
        pendingWhatsappCode($user, '+52551111111'.$user->id);
    }
    $this->deleteJson('/api/whatsapp-connection', [], $this->headers)->assertOk()->assertJsonPath('data.status', 'unlinked');
    $this->deleteJson('/api/whatsapp-connection', [], $this->headers)->assertOk();
    expect(WhatsAppConnection::query()->sole()->user_id)->toBe($other->id);
    expect(WhatsAppVerificationCode::query()->where('user_id', $this->user->id)->sole()->status)->toBe('invalidated');
    expect(WhatsAppVerificationCode::query()->where('user_id', $other->id)->sole()->status)->toBe('sent');
});

it('enforces the minute cooldown for the same user and phone across users', function (): void {
    $this->postJson('/api/whatsapp-verification-codes', ['phone_number' => '+525512345678'], $this->headers)->assertCreated();
    $this->postJson('/api/whatsapp-verification-codes', ['phone_number' => '+525587654321'], $this->headers)->assertStatus(429);
    $this->postJson('/api/whatsapp-verification-codes', ['phone_number' => '+525512345678'], whatsappHeaders(User::factory()->create()))->assertStatus(429);
    Http::assertSentCount(1);
});

it('limits users to five sends per hour even with different phones', function (): void {
    for ($index = 0; $index < 5; $index++) {
        $this->postJson('/api/whatsapp-verification-codes', ['phone_number' => '+52551234567'.$index], $this->headers)->assertCreated();
        $this->travel(61)->seconds();
    }
    $this->postJson('/api/whatsapp-verification-codes', ['phone_number' => '+525512345679'], $this->headers)->assertStatus(429);
    Http::assertSentCount(5);
});

it('limits a phone to five sends per hour across different users', function (): void {
    for ($index = 0; $index < 5; $index++) {
        $this->postJson('/api/whatsapp-verification-codes', ['phone_number' => '+525512345678'], whatsappHeaders(User::factory()->create()))->assertCreated();
        $this->travel(61)->seconds();
    }
    $this->postJson('/api/whatsapp-verification-codes', ['phone_number' => '+525512345678'], $this->headers)->assertStatus(429);
    Http::assertSentCount(5);
});

it('does not reactivate a code canceled while its send is in flight', function (): void {
    Http::fake(function () {
        app(\App\Services\WhatsApp\DisconnectWhatsAppConnection::class)->execute($this->user);

        return Http::response(['messages' => [['id' => 'wamid.test']]]);
    });
    $this->postJson('/api/whatsapp-verification-codes', ['phone_number' => '+525512345678'], $this->headers)->assertConflict();
    expect(WhatsAppVerificationCode::query()->sole()->status)->toBe('invalidated');
    $this->getJson('/api/whatsapp-connection', $this->headers)->assertJsonPath('data.status', 'unlinked');
});

it('requires exactly six digits as a string', function (mixed $code): void {
    $pending = pendingWhatsappCode($this->user);
    $this->postJson('/api/whatsapp-connection', ['code' => $code], $this->headers)
        ->assertUnprocessable()->assertJsonValidationErrors('code');
    expect($pending->fresh()->attempts)->toBe(0);
})->with(['number' => [123456], 'short' => ['12345'], 'long' => ['1234567'], 'letters' => ['abcdef']]);

it('enforces durable minute quotas independently of HTTP throttle counters', function (): void {
    $action = app(\App\Services\WhatsApp\RequestWhatsAppVerificationCode::class);
    $action->execute($this->user, '+525512345678');
    foreach ([[$this->user, '+525587654321'], [User::factory()->create(), '+525512345678']] as [$user, $phone]) {
        try {
            $action->execute($user, $phone);
            $this->fail('Expected the durable minute quota to reject the request.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            expect($exception->getStatusCode())->toBe(429);
            expect((int) $exception->getHeaders()['Retry-After'])->toBeGreaterThan(0);
        }
    }
    Http::assertSentCount(1);
    $this->assertDatabaseCount('whatsapp_verification_codes', 1);
});

it('enforces durable hourly quotas independently of HTTP throttle counters', function (bool $samePhone): void {
    $action = app(\App\Services\WhatsApp\RequestWhatsAppVerificationCode::class);
    for ($index = 0; $index < 5; $index++) {
        $action->execute($samePhone ? User::factory()->create() : $this->user, $samePhone ? '+525512345678' : '+52551234567'.$index);
        $this->travel(61)->seconds();
    }
    try {
        $action->execute($this->user, $samePhone ? '+525512345678' : '+525512345679');
        $this->fail('Expected the durable hourly quota to reject the request.');
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
        expect($exception->getStatusCode())->toBe(429);
    }
    Http::assertSentCount(5);
    $this->assertDatabaseCount('whatsapp_verification_codes', 5);
})->with(['per user' => false, 'per phone' => true]);

it('reserves a phone quota before its external send finishes', function (): void {
    $other = User::factory()->create();
    Http::fake(function () use ($other) {
        try {
            app(\App\Services\WhatsApp\RequestWhatsAppVerificationCode::class)->execute($other, '+525512345678');
            $this->fail('Expected the in-flight reservation to reject a competing send.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            expect($exception->getStatusCode())->toBe(429);
        }

        return Http::response(['messages' => [['id' => 'wamid.test']]]);
    });
    app(\App\Services\WhatsApp\RequestWhatsAppVerificationCode::class)->execute($this->user, '+525512345678');
    Http::assertSentCount(1);
    $this->assertDatabaseCount('whatsapp_verification_codes', 1);
    expect(WhatsAppVerificationCode::query()->sole()->status)->toBe('sent');
});
