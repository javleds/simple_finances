import assert from 'node:assert/strict';
import { mkdtemp, mkdir, copyFile, writeFile, readFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawnSync } from 'node:child_process';
import test from 'node:test';

async function deploy({ first = false, fail = '' } = {}) {
    const directory = await mkdtemp(join(tmpdir(), 'fin-si-deploy-'));
    try {
        await mkdir(join(directory, 'bin'));
        await copyFile(resolve('deploy.sh'), join(directory, 'deploy.sh'));
        await writeFile(join(directory, '.env'), ['TRAEFIK_APP_NAME', 'APP_DOMAIN', 'API_DOMAIN', 'APP_URL', 'SPA_URL', 'FRONTEND_URL'].map(key => `${key}=test`).join('\n'));
        const executable = async (name, script) => writeFile(join(directory, 'bin', name), `#!/usr/bin/env bash\n${script}`, { mode: 0o755 });
        await executable('flock', 'exit 0\n');
        await executable('git', `printf 'git %s\\n' "$*" >> "$TRACE"\ncase "$1" in\nstatus) exit 0;;\nrev-parse) echo abc123;;\npull) touch "$UPDATED";;\nesac\n`);
        await executable('docker', `printf 'docker %s\\n' "$*" >> "$TRACE"\nif [[ -n "$FAIL" && "$*" == *"$FAIL"* ]]; then exit 1; fi\nif [[ "$*" == *"config --services"* ]]; then\n echo php\n if [[ "$FIRST" != 1 || -f "$UPDATED" ]]; then printf 'attachments-worker\\nattachments-maintenance\\n'; fi\nfi\nif [[ "$*" == *"ps -q php"* && "$FIRST" != 1 ]]; then echo running; fi\n`);
        const trace = join(directory, 'trace');
        const result = spawnSync('bash', [join(directory, 'deploy.sh')], {
            env: { ...process.env, PATH: `${join(directory, 'bin')}:${process.env.PATH}`, TRACE: trace, UPDATED: join(directory, 'updated'), FIRST: first ? '1' : '0', FAIL: fail, DEPLOY_LOG_DIR: join(directory, 'logs') },
            encoding: 'utf8',
        });
        return { status: result.status, trace: await readFile(trace, 'utf8') };
    } finally {
        await rm(directory, { recursive: true, force: true });
    }
}

test('deployment stops consumers before updating code and diagnoses after migration', async () => {
    const { status, trace } = await deploy();
    assert.equal(status, 0);
    assert.ok(trace.indexOf('stop --timeout 90 attachments-worker') < trace.indexOf('git pull'));
    assert.ok(trace.indexOf('stop --timeout 90 attachments-maintenance') < trace.indexOf('git pull'));
    assert.ok(trace.indexOf('app:post-deploy --migrate') < trace.indexOf('up -d --force-recreate attachments-worker'));
    assert.ok(trace.indexOf('attachments:diagnose --timeout=120') < trace.indexOf('php artisan up\n'));
});

test('first deployment supports the old Compose without attachment services', async () => {
    const { status, trace } = await deploy({ first: true });
    assert.equal(status, 0);
    assert.equal(trace.slice(0, trace.indexOf('git pull')).includes('stop --timeout'), false);
    assert.ok(trace.includes('up -d --force-recreate attachments-worker attachments-maintenance'));
});

test('diagnostic failure stops consumers and keeps maintenance active', async () => {
    const { status, trace } = await deploy({ fail: 'attachments:diagnose' });
    assert.notEqual(status, 0);
    assert.equal(trace.includes('php artisan up\n'), false);
    const failure = trace.indexOf('attachments:diagnose');
    assert.ok(trace.indexOf('stop --timeout 90 attachments-worker', failure) > failure);
    assert.ok(trace.indexOf('stop --timeout 90 attachments-maintenance', failure) > failure);
});

test('build failure never starts attachment consumers or reopens traffic', async () => {
    const { status, trace } = await deploy({ fail: 'build --pull php' });
    assert.notEqual(status, 0);
    assert.equal(trace.includes('up -d --force-recreate attachments-worker'), false);
    assert.equal(trace.includes('php artisan up\n'), false);
});
