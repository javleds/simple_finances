<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, interactive-widget=resizes-content">
        <meta name="app-whatsapp-enabled" content="{{ app(\App\Services\WhatsApp\IsWhatsAppConfigured::class)->execute() ? 'true' : 'false' }}">
        <link rel="icon" href="/favicon.ico">
        <title>fin-si</title>
        @vite('resources/js/main.ts')
    </head>
    <body>
        <div id="app"></div>
    </body>
</html>
