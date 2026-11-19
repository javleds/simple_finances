<?php

declare(strict_types=1);

try {
    require __DIR__ . '/../vendor/autoload.php';
    $app = require __DIR__ . '/../bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    if (($argv[1] ?? '') === '--legal-hashes') {
        $legal = $app->make(App\Services\Legal\GetLegalDocuments::class)->execute();
        if (! $legal['available']) {
            throw new RuntimeException('Legal documents are unavailable.');
        }
        fwrite(STDOUT, json_encode([
            'terms' => $legal['documents']['terms']['hash'],
            'privacy' => $legal['documents']['privacy']['hash'],
        ], JSON_THROW_ON_ERROR) . "\n");
        exit(0);
    }
    $response = Illuminate\Support\Facades\Http::timeout(10)
        ->withToken((string) config('chat.token'))
        ->head(rtrim((string) config('chat.url'), '/') . '/internal/v1/threads/00000000-0000-0000-0000-000000000000', ['actor_user_id' => 1, 'channel' => 'web']);
    if (404 !== $response->status()) {
        throw new RuntimeException('Unexpected internal API response.');
    }
    fwrite(STDOUT, "Finance to agent authentication check passed.\n");
} catch (Throwable) {
    fwrite(STDERR, "Finance agent or legal-document deployment check failed.\n");
    exit(1);
}
