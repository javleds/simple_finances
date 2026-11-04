<?php

function loadCorsConfig(array $environment): array
{
    $originalEnvironment = [];

    foreach ($environment as $key => $value) {
        $originalEnvironment[$key] = [
            'env' => $_ENV[$key] ?? null,
            'server' => $_SERVER[$key] ?? null,
            'putenv' => getenv($key) === false ? null : getenv($key),
        ];

        if ($value === null) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);

            continue;
        }

        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv("{$key}={$value}");
    }

    try {
        return require base_path('config/cors.php');
    } finally {
        foreach ($originalEnvironment as $key => $originalValue) {
            if ($originalValue['env'] === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $originalValue['env'];
            }

            if ($originalValue['server'] === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $originalValue['server'];
            }

            if ($originalValue['putenv'] === null) {
                putenv($key);
            } else {
                putenv("{$key}={$originalValue['putenv']}");
            }
        }
    }
}

it('uses laravel default cors paths and allows every configured origin', function () {
    $corsConfiguration = loadCorsConfig([
        'CORS_ALLOWED_ORIGINS' => '*',
        'CORS_ALLOWED_ORIGIN_PATTERNS' => null,
    ]);

    expect($corsConfiguration['allowed_origins'])->toBe(['*']);
    expect($corsConfiguration['allowed_origins_patterns'])->toBe([]);
    expect($corsConfiguration['paths'])->toBe(['api/*', 'sanctum/csrf-cookie']);
});

it('supports allowed origin patterns from environment configuration', function () {
    $corsConfiguration = loadCorsConfig([
        'CORS_ALLOWED_ORIGINS' => '',
        'CORS_ALLOWED_ORIGIN_PATTERNS' => '#^https?://([a-z0-9-]+\.)*fin-si\.com(?::\d+)?$#i',
    ]);
    $allowedOriginPattern = $corsConfiguration['allowed_origins_patterns'][0];

    expect($corsConfiguration['allowed_origins'])->toBe([]);
    expect($allowedOriginPattern)->toBeString();
    expect(preg_match($allowedOriginPattern, 'https://fin-si.com'))->toBe(1);
    expect(preg_match($allowedOriginPattern, 'https://app.fin-si.com'))->toBe(1);
    expect(preg_match($allowedOriginPattern, 'https://deep.api.fin-si.com'))->toBe(1);
    expect(preg_match($allowedOriginPattern, 'https://fin-si.com.evil.com'))->toBe(0);
    expect(preg_match($allowedOriginPattern, 'https://evil-fin-si.com'))->toBe(0);
});
