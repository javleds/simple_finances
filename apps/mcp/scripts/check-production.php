<?php

declare(strict_types=1);

function hasOnlyReadPrivileges(array $grants): bool
{
    if ([] === $grants) {
        return false;
    }

    foreach ($grants as $grant) {
        if (str_contains(strtoupper($grant), 'WITH GRANT OPTION') || ! preg_match('/^GRANT (.+) ON .+ TO /i', $grant, $matches)) {
            return false;
        }
        foreach (explode(',', strtoupper($matches[1])) as $privilege) {
            if (! in_array(trim($privilege), ['SELECT', 'USAGE'], true)) {
                return false;
            }
        }
    }

    return true;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) {
    return;
}

try {
    require __DIR__ . '/../vendor/autoload.php';
    $app = require __DIR__ . '/../bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $expected = json_decode($argv[1] ?? '', true, flags: JSON_THROW_ON_ERROR);
    if (! is_array($expected) || empty($expected['terms']) || empty($expected['privacy'])
        || $expected['terms'] !== config('mcp_service.terms_hash')
        || $expected['privacy'] !== config('mcp_service.privacy_hash')) {
        throw new RuntimeException('MCP legal hashes differ from Finance.');
    }
    $connection = $app->make('db')->connection();
    $grants = array_map(static fn (object $row): string => (string) array_values((array) $row)[0], $connection->select('SHOW GRANTS FOR CURRENT_USER'));
    if (! hasOnlyReadPrivileges($grants)) {
        throw new RuntimeException('MCP requires SELECT-only database privileges.');
    }
    $connection->select('SELECT id FROM accounts LIMIT 0');
    $connection->select('SELECT id FROM users LIMIT 0');
    fwrite(STDOUT, "MCP read-only database check passed.\n");
} catch (Throwable) {
    fwrite(STDERR, "MCP database check failed. Verify legal hashes, credentials, schema and SELECT-only grants.\n");
    exit(1);
}
