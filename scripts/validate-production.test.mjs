import assert from 'node:assert/strict';
import { copyFileSync, mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { spawnSync } from 'node:child_process';
import test from 'node:test';
import { validateProduction } from './validate-production.mjs';

function fixture(t, chat = true) {
    const root = mkdtempSync(join(tmpdir(), 'fin-si-production-'));
    t.after(() => rmSync(root, { recursive: true, force: true }));
    const documents = {
        'apps/finance/.env': {
            COMPOSE_PROJECT_NAME: 'retained-finance', APP_ENV: 'production', APP_DEBUG: 'false',
            CHAT_ENABLED: String(chat), DB_CONNECTION: 'mysql', DB_HOST: 'mysql', DB_PORT: '3306',
            DB_DATABASE: 'finance', DB_USERNAME: 'finance_writer', DB_PASSWORD: 'writer-test-password',
            FINANCE_AGENT_SERVICE_TOKEN: 'finance-test-token-'.repeat(3),
        },
        '.env': {
            COMPOSE_PROJECT_NAME: 'retained-finance', DB_DATABASE: 'finance', DB_PASSWORD: 'writer-test-password',
            AGENT_DATABASE_OWNER_PASSWORD: 'owner-test-password', AGENT_DATABASE_PASSWORD: 'runtime test:@password',
        },
        'apps/mcp/.env': {
            APP_ENV: 'production', APP_DEBUG: 'false', APP_KEY: 'base64:test-only-key',
            DB_CONNECTION: 'mysql', DB_HOST: 'mysql', DB_PORT: '3306', DB_DATABASE: 'finance',
            DB_USERNAME: 'finance_reader', DB_PASSWORD: 'reader-test-password',
            AGENT_MCP_SERVICE_TOKEN: 'mcp-test-token-'.repeat(3), MCP_LEGAL_TERMS_HASH: 'terms-test-hash',
            MCP_LEGAL_PRIVACY_HASH: 'privacy-test-hash',
        },
        'apps/agents/.env': {
            DATABASE_URL: 'postgresql+psycopg://agent_runtime:runtime%20test%3A%40password@agent-postgres:5432/agent',
            FINANCE_AGENT_SERVICE_TOKEN: 'finance-test-token-'.repeat(3),
            AGENT_MCP_SERVICE_TOKEN: 'mcp-test-token-'.repeat(3),
            MCP_URL: 'http://agent-mcp:8080/mcp', OPENROUTER_API_KEY: 'fake-key-never-used', AGENT_MODEL: 'test/model',
        },
    };
    const write = (file, updates = {}) => {
        Object.assign(documents[file], updates);
        mkdirSync(dirname(join(root, file)), { recursive: true });
        writeFileSync(join(root, file), Object.entries(documents[file]).filter(([, value]) => value !== undefined).map(([key, value]) => `${key}='${value}'`).join('\n'));
    };
    for (const file of chat ? Object.keys(documents) : ['apps/finance/.env']) write(file);
    return { root, documents, write };
}

test('Finance-only deployment does not require other application env files', t => {
    const { root } = fixture(t, false);
    assert.equal(validateProduction(root), 'finance');
});

test('valid separate production configuration enables chat without modifying files or contacting providers', t => {
    const { root, write } = fixture(t);
    assert.equal(validateProduction(root), 'chat');
    write('.env', { COMPOSE_PROJECT_NAME: undefined, DB_DATABASE: undefined, DB_PASSWORD: undefined });
    assert.equal(validateProduction(root), 'chat');
});

for (const [value, enabled] of [['true', true], ['(true)', true], ['1', true], ['false', false], ['(false)', false], ['0', false], ['', false]]) {
    test(`accepts Laravel CHAT_ENABLED=${value}`, t => {
        const { root, write } = fixture(t);
        write('apps/finance/.env', { CHAT_ENABLED: value });
        assert.equal(validateProduction(root), enabled ? 'chat' : 'finance');
    });
}

const invalidConfigurations = [
    ['apps/finance/.env', 'CHAT_ENABLED', 'yes', /Invalid boolean/],
    ['apps/finance/.env', 'APP_ENV', 'local', /APP_ENV/],
    ['apps/finance/.env', 'APP_DEBUG', 'true', /APP_DEBUG/],
    ['apps/finance/.env', 'COMPOSE_PROJECT_NAME', 'Other/Project', /COMPOSE_PROJECT_NAME/],
    ['.env', 'COMPOSE_PROJECT_NAME', 'fin-si-chat', /COMPOSE_PROJECT_NAME/],
    ['.env', 'DB_DATABASE', 'another-db', /DB_DATABASE/],
    ['.env', 'DB_PASSWORD', 'wrong-password', /DB_PASSWORD/],
    ['apps/finance/.env', 'AGENT_DATABASE_OWNER_PASSWORD', 'override', /must not override/],
    ['apps/finance/.env', 'AGENT_DATABASE_PASSWORD', 'override', /must not override/],
    ['.env', 'AGENT_DATABASE_OWNER_PASSWORD', 'unsafe:@password', /URL-safe/],
    ['.env', 'AGENT_DATABASE_PASSWORD', '', /AGENT_DATABASE_PASSWORD/],
    ['apps/agents/.env', 'DATABASE_URL', 'private-key@%%invalid-uri', /DATABASE_URL/],
    ['apps/agents/.env', 'DATABASE_URL', 'postgresql+psycopg://agent_owner:owner-test-password@agent-postgres:5432/agent', /agent_runtime/],
    ['apps/agents/.env', 'DATABASE_URL', 'postgresql+psycopg://agent_runtime:wrong@agent-postgres:5432/agent', /DATABASE_URL/],
    ['apps/agents/.env', 'FINANCE_AGENT_SERVICE_TOKEN', 'other-secret', /FINANCE_AGENT_SERVICE_TOKEN/],
    ['apps/agents/.env', 'AGENT_MCP_SERVICE_TOKEN', 'other-secret', /AGENT_MCP_SERVICE_TOKEN/],
    ['apps/agents/.env', 'OPENROUTER_API_KEY', '', /OPENROUTER_API_KEY/],
    ['apps/agents/.env', 'AGENT_MODEL', '', /AGENT_MODEL/],
    ['apps/agents/.env', 'MCP_URL', 'https://public-mcp.example/mcp', /internal Docker/],
    ['apps/finance/.env', 'CHAT_AGENT_URL', 'http://localhost:8001', /internal Docker/],
    ['apps/mcp/.env', 'APP_ENV', 'local', /APP_ENV/],
    ['apps/mcp/.env', 'APP_DEBUG', 'true', /APP_DEBUG/],
    ['apps/mcp/.env', 'APP_KEY', '', /APP_KEY/],
    ['apps/mcp/.env', 'DB_HOST', 'host.docker.internal', /internal mysql/],
    ['apps/mcp/.env', 'DB_DATABASE', 'other-database', /DB_DATABASE/],
    ['apps/mcp/.env', 'DB_USERNAME', 'root', /separate non-root/],
    ['apps/mcp/.env', 'DB_USERNAME', 'finance_writer', /separate non-root/],
    ['apps/mcp/.env', 'DB_PASSWORD', '', /DB_PASSWORD/],
    ['apps/mcp/.env', 'MCP_LEGAL_TERMS_HASH', '', /MCP_LEGAL_TERMS_HASH/],
    ['apps/mcp/.env', 'MCP_LEGAL_PRIVACY_HASH', '', /MCP_LEGAL_PRIVACY_HASH/],
];
for (const [file, key, value, expected] of invalidConfigurations) {
    test(`rejects unsafe ${key} in ${file}`, t => {
        const { root, write } = fixture(t);
        write(file, { [key]: value });
        assert.throws(() => validateProduction(root), expected);
    });
}

test('rejects matching short service tokens and reused boundary credentials', t => {
    const { root, write } = fixture(t);
    write('apps/finance/.env', { FINANCE_AGENT_SERVICE_TOKEN: 'short' });
    write('apps/agents/.env', { FINANCE_AGENT_SERVICE_TOKEN: 'short' });
    assert.throws(() => validateProduction(root), /32 non-whitespace/);
    const reused = 'reused-test-token-'.repeat(3);
    write('apps/finance/.env', { FINANCE_AGENT_SERVICE_TOKEN: reused });
    write('apps/agents/.env', { FINANCE_AGENT_SERVICE_TOKEN: reused, AGENT_MCP_SERVICE_TOKEN: reused });
    write('apps/mcp/.env', { AGENT_MCP_SERVICE_TOKEN: reused });
    assert.throws(() => validateProduction(root), /must differ/);
});

test('CLI derives root from script location and reports errors without secrets', t => {
    const { root, write, documents } = fixture(t);
    mkdirSync(join(root, 'scripts'));
    copyFileSync(new URL('./validate-production.mjs', import.meta.url), join(root, 'scripts/validate-production.mjs'));
    const run = () => spawnSync(process.execPath, ['../../scripts/validate-production.mjs'], { cwd: join(root, 'apps/finance'), encoding: 'utf8' });
    const success = run();
    assert.equal(success.status, 0);
    assert.equal(success.stdout, 'chat\n');
    assert.equal(success.stderr, '');
    const secret = 'private-key-not-a-valid-url';
    write('apps/agents/.env', { DATABASE_URL: secret });
    const failure = run();
    assert.equal(failure.status, 1);
    assert.equal(failure.stdout, '');
    assert.match(failure.stderr, /DATABASE_URL/);
    for (const value of [secret, documents['apps/agents/.env'].OPENROUTER_API_KEY, documents['apps/finance/.env'].FINANCE_AGENT_SERVICE_TOKEN]) {
        assert.equal(failure.stderr.includes(value), false);
    }
});
