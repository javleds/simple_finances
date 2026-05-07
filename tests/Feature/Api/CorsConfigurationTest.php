<?php

function loadCorsConfigForEnvironment(string $environment): array
{
    $originalAppEnv = $_ENV['APP_ENV'] ?? null;
    $originalServerAppEnv = $_SERVER['APP_ENV'] ?? null;

    putenv("APP_ENV={$environment}");
    $_ENV['APP_ENV'] = $environment;
    $_SERVER['APP_ENV'] = $environment;

    try {
        return require base_path('config/cors.php');
    } finally {
        if ($originalAppEnv === null) {
            unset($_ENV['APP_ENV']);
            putenv('APP_ENV');
        } else {
            $_ENV['APP_ENV'] = $originalAppEnv;
            putenv("APP_ENV={$originalAppEnv}");
        }

        if ($originalServerAppEnv === null) {
            unset($_SERVER['APP_ENV']);
        } else {
            $_SERVER['APP_ENV'] = $originalServerAppEnv;
        }
    }
}

it('allows every origin in local environment', function () {
    $corsConfiguration = loadCorsConfigForEnvironment('local');

    expect($corsConfiguration['allowed_origins'])->toBe(['*']);
    expect($corsConfiguration['allowed_origins_patterns'])->toBe([]);
    expect($corsConfiguration['paths'])->toBe(['api/*']);
});

it('allows only fin-si domains in production environment', function () {
    $corsConfiguration = loadCorsConfigForEnvironment('production');
    $allowedOriginPattern = $corsConfiguration['allowed_origins_patterns'][0];

    expect($corsConfiguration['allowed_origins'])->toBe([]);
    expect($allowedOriginPattern)->toBeString();
    expect(preg_match($allowedOriginPattern, 'https://fin-si.com'))->toBe(1);
    expect(preg_match($allowedOriginPattern, 'https://app.fin-si.com'))->toBe(1);
    expect(preg_match($allowedOriginPattern, 'https://deep.api.fin-si.com'))->toBe(1);
    expect(preg_match($allowedOriginPattern, 'https://fin-si.com.evil.com'))->toBe(0);
    expect(preg_match($allowedOriginPattern, 'https://evil-fin-si.com'))->toBe(0);
});
