<?php

use App\Services\WhatsApp\IsWhatsAppConfigured;

beforeEach(function (): void {
    config()->set('services.whatsapp', [
        'phone_number_id' => 'test-phone-id',
        'access_token' => 'test-access-token',
        'verify_token' => 'test-verify-token',
        'app_secret' => 'test-app-secret',
        'auth_template_name' => 'test-auth-template',
        'auth_template_language' => 'es_MX',
    ]);
});

it('enables WhatsApp when the integration configuration is complete', function (): void {
    expect(app(IsWhatsAppConfigured::class)->execute())->toBeTrue();
});

it('disables WhatsApp when a required value is missing or invalid', function (string $key, mixed $value): void {
    config()->set('services.whatsapp.'.$key, $value);

    expect(app(IsWhatsAppConfigured::class)->execute())->toBeFalse();
})->with([
    'phone_number_id', 'access_token', 'verify_token', 'app_secret', 'auth_template_name', 'auth_template_language',
])->with([null, '', '   ', 123]);

it('exposes only availability in the SPA shell', function (): void {
    $response = $this->withoutVite()->get('/admin/settings');

    $response->assertOk()->assertSee('<meta name="app-whatsapp-enabled" content="true">', false);

    foreach (['test-phone-id', 'test-access-token', 'test-verify-token', 'test-app-secret', 'test-auth-template'] as $secret) {
        $response->assertDontSee($secret, false);
    }
});

it('marks the SPA integration unavailable when configuration is incomplete', function (): void {
    config()->set('services.whatsapp.access_token', null);

    $this->withoutVite()->get('/admin/settings')
        ->assertOk()
        ->assertSee('<meta name="app-whatsapp-enabled" content="false">', false);
});
