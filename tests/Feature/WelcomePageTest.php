<?php

it('serves the Vue application entrypoint', function (string $path): void {
    $this->withoutVite();

    $this->get($path)
        ->assertOk()
        ->assertViewIs('spa')
        ->assertSee('<div id="app"></div>', false)
        ->assertSee('<html lang="es">', false);
})->with(['/', '/auth', '/admin/accounts', '/unknown-client-route']);

it('does not serve Vue for reserved paths or missing assets', function (string $path): void {
    $this->getJson($path)
        ->assertNotFound()
        ->assertDontSee('<div id="app"></div>', false);
})->with(['/api', '/api/missing', '/build/missing.js', '/storage/missing', '/missing.ico']);

it('preserves API authentication and healthcheck routes', function (): void {
    $this->getJson('/api/profile')->assertUnauthorized();
    $this->get('/up')->assertOk()->assertDontSee('<div id="app"></div>', false);
});

it('does not accept writes to client routes', function (): void {
    $this->postJson('/admin/accounts')->assertStatus(405);
});
