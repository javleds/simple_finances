<?php

declare(strict_types=1);

return [
    'enabled' => env('CHAT_ENABLED', false),
    'url'     => env('CHAT_AGENT_URL', 'http://agent-api:8000'),
    'token'   => env('FINANCE_AGENT_SERVICE_TOKEN'),
];
