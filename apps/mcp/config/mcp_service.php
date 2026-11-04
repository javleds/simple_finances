<?php

declare(strict_types=1);

return [
    'token'        => env('AGENT_MCP_SERVICE_TOKEN', ''),
    'terms_hash'   => env('MCP_LEGAL_TERMS_HASH', ''),
    'privacy_hash' => env('MCP_LEGAL_PRIVACY_HASH', ''),
];
