<?php

$isLocalEnvironment = env('APP_ENV') === 'local';

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => $isLocalEnvironment ? ['*'] : [],
    'allowed_origins_patterns' => $isLocalEnvironment
        ? []
        : ['#^https?://([a-z0-9-]+\.)*fin-si\.com(?::\d+)?$#i'],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
