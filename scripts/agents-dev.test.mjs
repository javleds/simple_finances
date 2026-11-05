import { spawn } from 'node:child_process';
import { copyFileSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import assert from 'node:assert/strict';
import test from 'node:test';
import { developmentEnvironment } from './agents-dev.mjs';

const finance = { DB_CONNECTION: 'mysql', DB_HOST: '127.0.0.1', DB_PORT: '3311', DB_DATABASE: 'existing', FINANCE_AGENT_SERVICE_TOKEN: 'finance-test' };
const agent = { FINANCE_AGENT_SERVICE_TOKEN: 'finance-test', AGENT_MCP_SERVICE_TOKEN: 'mcp-test' };
const mcp = { AGENT_MCP_SERVICE_TOKEN: 'mcp-test', DB_USERNAME: 'reader', DB_PASSWORD: 'test', MCP_LEGAL_TERMS_HASH: 'test', MCP_LEGAL_PRIVACY_HASH: 'test' };

test('host runtime uses existing Finance database endpoint without copying write credentials', () => {
    assert.deepEqual(developmentEnvironment(finance, agent, mcp), {
        DEV_FINANCE_DB_HOST: 'host.docker.internal', DEV_FINANCE_DB_PORT: '3311', DEV_FINANCE_DB_DATABASE: 'existing',
    });
});
test('remote database hostname is preserved', () => {
    assert.equal(developmentEnvironment({ ...finance, DB_HOST: 'local-db' }, agent, mcp).DEV_FINANCE_DB_HOST, 'local-db');
});
test('mismatched service identities fail before starting services', () => {
    assert.throws(() => developmentEnvironment(finance, { ...agent, FINANCE_AGENT_SERVICE_TOKEN: 'other' }, mcp), /matching/);
    assert.throws(() => developmentEnvironment(finance, agent, { ...mcp, AGENT_MCP_SERVICE_TOKEN: 'other' }), /matching/);
});
test('missing reader configuration and unsupported database fail explicitly', () => {
    assert.throws(() => developmentEnvironment(finance, agent, { ...mcp, DB_PASSWORD: '' }), /read-only/);
    assert.throws(() => developmentEnvironment({ ...finance, DB_CONNECTION: 'sqlite' }, agent, mcp), /mysql/);
});


for (const scenario of ['studio-failure', 'interrupt', 'build-failure']) {
    test(`runtime cleanup: ${scenario}`, async t => {
        const root = mkdtempSync(join(tmpdir(), 'agent-dev-'));
        t.after(() => rmSync(root, { recursive: true, force: true }));
        for (const folder of ['scripts', 'bin', 'apps/finance', 'apps/agents', 'apps/mcp']) mkdirSync(join(root, folder), { recursive: true });
        for (const file of ['agents-dev.mjs', 'chat-config.mjs']) copyFileSync(new URL(file, import.meta.url), join(root, 'scripts', file));
        for (const [app, values] of Object.entries({ finance, agents: agent, mcp })) {
            writeFileSync(join(root, 'apps', app, '.env'), Object.entries(values).map(([key, value]) => `${key}=${value}`).join('\n'));
        }
        const log = join(root, 'calls');
        writeFileSync(join(root, 'bin/docker'), `#!/bin/sh\necho "docker $*" >> "$DEV_TEST_LOG"\ncase "$*" in\n*'build agent-api mcp-php'*) exit ${scenario === 'build-failure' ? 7 : 0};;\n*'--abort-on-container-exit'*) exec sleep 30;;\nesac\n`, { mode: 0o755 });
        writeFileSync(join(root, 'bin/uv'), `#!/bin/sh\necho "uv $* MCP_URL=$MCP_URL" >> "$DEV_TEST_LOG"\necho studio-ready\n${scenario === 'studio-failure' ? 'exit 9' : 'exec sleep 30'}\n`, { mode: 0o755 });
        const child = spawn(process.execPath, [join(root, 'scripts/agents-dev.mjs')], {
            env: { ...process.env, PATH: `${join(root, 'bin')}:/usr/bin:/bin`, DEV_TEST_LOG: log },
            stdio: ['ignore', 'pipe', 'pipe'],
        });
        t.after(() => child.kill('SIGKILL'));
        const timer = setTimeout(() => child.kill('SIGKILL'), 5000);
        if (scenario === 'interrupt') {
            child.stdout.on('data', data => { if (data.toString().includes('studio-ready')) child.kill('SIGTERM'); });
        }
        const status = await new Promise(resolve => child.once('exit', resolve));
        clearTimeout(timer);
        assert.equal(status, scenario === 'interrupt' ? 143 : 1);
        const calls = readFileSync(log, 'utf8');
        assert.match(calls, /stop -t 2 agent-api agent-worker agent-mcp mcp-php/);
        if (scenario === 'build-failure') assert.doesNotMatch(calls, /up -d|uv /);
        else assert.match(calls, /--port 2024 --no-browser MCP_URL=http:\/\/127.0.0.1:8002\/mcp/);
        assert.doesNotMatch(calls, /up.*mysql/);
    });
}
