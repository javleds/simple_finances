import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, copyFileSync, readFileSync, writeFileSync, rmSync, statSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { configure, readSettings } from './chat-config.mjs';

function fixture(t) {
    const root = mkdtempSync(join(tmpdir(), 'chat-config-'));
    t.after(() => rmSync(root, { recursive: true, force: true }));
    for (const file of ['.env.example', 'apps/finance/.env.example', 'apps/mcp/.env.example', 'apps/agents/.env.example']) {
        mkdirSync(dirname(join(root, file)), { recursive: true });
        copyFileSync(new URL(`../${file}`, import.meta.url), join(root, file));
    }
    return root;
}

test('prepares isolated app configuration and remains stable on repetition', t => {
    const root = fixture(t);
    configure(root);
    const infra = readSettings(root, '.env');
    const finance = readSettings(root, 'apps/finance/.env');
    const mcp = readSettings(root, 'apps/mcp/.env');
    const agent = readSettings(root, 'apps/agents/.env');
    assert.equal(infra.OPENROUTER_API_KEY, undefined);
    assert.equal(finance.FINANCE_AGENT_SERVICE_TOKEN, agent.FINANCE_AGENT_SERVICE_TOKEN);
    assert.equal(mcp.AGENT_MCP_SERVICE_TOKEN, agent.AGENT_MCP_SERVICE_TOKEN);
    assert.equal(finance.DB_PASSWORD, infra.DB_PASSWORD);
    assert.ok(agent.DATABASE_URL.includes(infra.AGENT_DATABASE_PASSWORD));
    assert.notEqual(mcp.DB_PASSWORD, infra.DB_PASSWORD);
    const before = readFileSync(join(root, 'apps/agents/.env'), 'utf8');
    configure(root);
    assert.equal(readFileSync(join(root, 'apps/agents/.env'), 'utf8'), before);
    assert.equal(statSync(join(root, 'apps/agents/.env')).mode & 0o777, 0o600);
});

test('preserves existing Finance database and service credential', t => {
    const root = fixture(t);
    writeFileSync(join(root, 'apps/finance/.env'), 'DB_HOST=existing-host\nDB_PASSWORD="keep # this"\nFINANCE_AGENT_SERVICE_TOKEN=existing-token\n');
    configure(root);
    const finance = readSettings(root, 'apps/finance/.env');
    assert.equal(finance.DB_HOST, 'existing-host');
    assert.equal(finance.DB_PASSWORD, 'keep # this');
    assert.equal(readSettings(root, 'apps/agents/.env').FINANCE_AGENT_SERVICE_TOKEN, 'existing-token');
});

test('rejects conflicting credentials before writing any file', t => {
    const root = fixture(t);
    configure(root);
    const path = join(root, 'apps/agents/.env');
    const content = readFileSync(path, 'utf8').replace(/^FINANCE_AGENT_SERVICE_TOKEN=.*$/m, 'FINANCE_AGENT_SERVICE_TOKEN=conflict');
    writeFileSync(path, content);
    assert.throws(() => configure(root), /differs between/);
    assert.equal(readFileSync(path, 'utf8'), content);
});

test('requires deliberate legacy migration without touching credentials', t => {
    const root = fixture(t);
    writeFileSync(join(root, '.env.chat'), 'DB_PASSWORD=old-secret\n');
    assert.throws(() => configure(root), /Migrate .env.chat/);
    assert.equal(readFileSync(join(root, '.env.chat'), 'utf8'), 'DB_PASSWORD=old-secret\n');
});
