@php
    $legal = app(\App\Services\Legal\GetLegalDocuments::class)->execute();
    $documentKey = match (request()->path()) {
        'auth/terms-and-conditions' => 'terms',
        'auth/privacy-policy' => 'privacy',
        default => null,
    };
    $document = $documentKey === null ? null : $legal['documents'][$documentKey];
@endphp
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, interactive-widget=resizes-content">
        <meta name="app-whatsapp-enabled" content="{{ app(\App\Services\WhatsApp\IsWhatsAppConfigured::class)->execute() ? 'true' : 'false' }}">
        <link rel="icon" href="/favicon.ico?v=3" sizes="16x16 32x32 48x48">
        <link rel="icon" type="image/png" sizes="32x32" href="/brand/favicon-32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="/brand/favicon-16.png">
        <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
        <title>fin-si</title>
        @vite('resources/js/main.ts')
    </head>
    <body>
        <div id="app">
            @if ($document !== null)
                <main class="mx-auto max-w-2xl space-y-6 px-4 py-10">
                    <h1 class="text-2xl font-semibold">{{ $document['title'] }}</h1>
                    @if ($legal['available'])
                        <p>Versión {{ $document['version'] }}</p>
                        <address class="space-y-1 not-italic">
                            <p>{{ $legal['provider']['name'] }} · {{ $legal['provider']['brand'] }}</p>
                            <p>RFC: {{ $legal['provider']['rfc'] }}</p>
                            <p>{{ $legal['provider']['address'] }}</p>
                            <p>{{ $legal['provider']['email'] }} · {{ $legal['provider']['phone'] }}</p>
                            <p>{{ $legal['provider']['site_url'] }}</p>
                        </address>
                        @foreach ($document['sections'] as $section)
                            <section class="space-y-3">
                                <h2 class="text-lg font-semibold">{{ $section['title'] }}</h2>
                                @foreach ($section['paragraphs'] as $paragraph)
                                    <p>{{ $paragraph }}</p>
                                @endforeach
                            </section>
                        @endforeach
                    @else
                        <p>La información legal no está disponible temporalmente. Intenta nuevamente más tarde.</p>
                    @endif
                </main>
            @endif
        </div>
        <footer class="bg-(--app-color-page) px-4 py-6 text-center text-sm text-(--app-color-text-muted)">
            @if ($legal['provider']['name'] !== '')
                <p>Fin-si es un servicio de {{ $legal['provider']['name'] }}@if ($legal['provider']['brand'] !== ''), quien opera bajo la marca {{ $legal['provider']['brand'] }}@endif.</p>
            @endif
            <a href="/auth/privacy-policy" class="underline">Política de privacidad</a>
            <a href="/auth/terms-and-conditions" class="ml-4 underline">Términos y condiciones</a>
        </footer>
    </body>
</html>
