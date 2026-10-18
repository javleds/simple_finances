<?php

declare(strict_types=1);

beforeEach(function () {
    config()->set('legal.provider', [
        'name'     => 'Titular Legal de Prueba',
        'rfc'      => 'TEST010101AAA',
        'address'  => 'Domicilio de prueba, México',
        'email'    => 'privacy@example.com',
        'phone'    => '4420000000',
        'site_url' => 'https://example.com',
        'brand'    => 'Marca de prueba',
    ]);
});

it('renders the full canonical legal document in the initial public HTML', function (string $path, string $document) {
    $legal = app(App\Services\Legal\GetLegalDocuments::class)->execute();
    $response = $this->get($path)->assertOk()->assertSee('Titular Legal de Prueba');

    foreach ($legal['documents'][$document]['sections'] as $section) {
        $response->assertSee($section['title']);
        foreach ($section['paragraphs'] as $paragraph) {
            $response->assertSee($paragraph);
        }
    }
})->with([
    ['/auth/privacy-policy', 'privacy'],
    ['/auth/terms-and-conditions', 'terms'],
]);

it('escapes legal provider fields rather than executing markup', function () {
    config()->set('legal.provider.name', '<script>alert("identity")</script>');

    $this->get('/auth/privacy-policy')->assertOk()
        ->assertSee('<script>alert("identity")</script>')
        ->assertDontSee('<script>alert("identity")</script>', false);
});

it('keeps the application and public legal routes available with incomplete legal identity', function () {
    config()->set('legal.provider.name', '');

    $this->get('/auth')->assertOk();
    $this->get('/auth/privacy-policy')->assertOk()
        ->assertSee('La información legal no está disponible temporalmente.');
});
