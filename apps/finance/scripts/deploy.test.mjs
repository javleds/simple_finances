import assert from 'node:assert/strict';
import { mkdtemp, mkdir, copyFile, writeFile, readFile, rm, stat } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawnSync } from 'node:child_process';
import test from 'node:test';

async function deploy({ first = false, fail = '', mismatchedIdentity = false, chat = false, existingChat = false, changedEntrypoint = false } = {}) {
    const directory = await mkdtemp(join(tmpdir(), 'fin-si-deploy-'));
    try {
        await mkdir(join(directory, 'bin'));
        await copyFile(resolve('../../deploy.sh'), join(directory, 'deploy.sh'));
        await mkdir(join(directory, 'apps/finance'), { recursive: true });
        await writeFile(join(directory, 'apps/finance/.env'), ['COMPOSE_PROJECT_NAME', 'TRAEFIK_APP_NAME', 'APP_DOMAIN', 'API_DOMAIN', 'APP_URL', 'SPA_URL', 'FRONTEND_URL'].map(key => `${key}=test`).join('\n'));
        const executable = async (name, script) => writeFile(join(directory, 'bin', name), `#!/usr/bin/env bash\n${script}`, { mode: 0o755 });
        await executable('flock', 'exit 0\n');
        await executable('git', `printf 'git %s\\n' "$*" >> "$TRACE"\ncase "$1" in\nstatus) exit 0;;\nrev-parse) echo abc123;;\nhash-object) if [[ "$CHANGED_ENTRYPOINT" == 1 && -f "$UPDATED" ]]; then echo new; else echo old; fi;;\npull) touch "$UPDATED";;\nesac\n`);
        await executable('docker', `printf 'docker %s\\n' "$*" >> "$TRACE"\nif [[ "$1" == inspect ]]; then if [[ "$MISMATCH" == 1 ]]; then echo another-project; exit 0; fi; exit 1; fi\nif [[ "$1" == ps && "$EXISTING_CHAT" == 1 ]]; then echo chat-container; fi\nif [[ "$*" == *"validate-production.mjs"* ]]; then if [[ "$CHAT" == 1 ]]; then echo chat; else echo finance; fi; fi\nif [[ -n "$FAIL" && "$*" == *"$FAIL"* ]]; then exit 1; fi\nif [[ "$*" == *"config --services"* ]]; then\n echo php\n if [[ "$FIRST" != 1 || -f "$UPDATED" ]]; then printf 'attachments-worker\\nattachments-maintenance\\n'; fi\nfi\nif [[ "$*" == *"ps -q php"* && "$FIRST" != 1 ]]; then echo running; fi\n`);
        const trace = join(directory, 'trace');
        const result = spawnSync('bash', [join(directory, 'deploy.sh')], {
            env: { ...process.env, PATH: `${join(directory, 'bin')}:${process.env.PATH}`, TRACE: trace, UPDATED: join(directory, 'updated'), FIRST: first ? '1' : '0', MISMATCH: mismatchedIdentity ? '1' : '0', FAIL: fail, CHAT: chat ? '1' : '0', EXISTING_CHAT: existingChat ? '1' : '0', CHANGED_ENTRYPOINT: changedEntrypoint ? '1' : '0', DEPLOY_LOG_DIR: join(directory, 'logs') },
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
    assert.equal(trace.slice(0, trace.indexOf('git pull')).includes('stop --timeout 90 attachments'), false);
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

test('deployment uses the application env file for every compose call', async () => {
    const { status, trace } = await deploy();
    assert.equal(status, 0);
    const commands = trace.split('\n').filter(line => line.startsWith('docker compose'));
    assert.ok(commands.length > 0);
    assert.ok(commands.every(line => /compose --project-name test --env-file .*\/apps\/finance\/.env -f docker-compose.prod.yml/.test(line)));
});

test('first layout transition preserves storage and configuration before starting the new entrypoint', async () => {
    const directory = await mkdtemp(join(tmpdir(), 'fin-si-transition-'));
    const repository = join(directory, 'checkout');
    const backup = join(directory, 'backup');
    const trace = join(directory, 'trace');
    try {
        await mkdir(join(directory, 'bin'));
        await mkdir(join(repository, 'storage/app/private'), { recursive: true });
        await mkdir(join(repository, 'public/build'), { recursive: true });
        await writeFile(join(repository, 'storage/app/private/receipt'), 'retained receipt');
        await writeFile(join(repository, 'public/build/manifest.json'), '{"old":true}');
        await writeFile(join(repository, '.env'), 'APP_KEY=retained-key\n');
        await writeFile(join(repository, 'artisan'), '');
        await writeFile(join(repository, 'docker-compose.prod.yml'), 'services: {}');
        const executable = async (name, script) => writeFile(join(directory, 'bin', name), `#!/usr/bin/env bash\n${script}`, { mode: 0o755 });
        await executable('flock', 'exit 0');
        await executable('docker', `printf 'docker %s\\n' "$*" >> "$TRACE"
if [[ "$1" == inspect ]]; then
  if [[ "$3" == *Mounts* ]]; then echo retained_simplefinancesdb; else echo retained; fi
fi
if [[ "$*" == *'config --services'* ]]; then printf 'php\\nnginx\\nattachments-worker\\nattachments-maintenance\\n'; fi
`);
        await executable('git', `printf 'git %s\\n' "$*" >> "$TRACE"
if [[ "$1" == rev-parse ]]; then echo abc123; fi
if [[ "$1" == checkout ]]; then
  mkdir -p apps/finance/storage apps/finance/public
  printf '#!/usr/bin/env bash\\nprintf "new-deploy %%s\\\\n" "$*" >> "$TRACE"\\n' > deploy.sh
fi
`);
        const result = spawnSync('bash', [resolve('../../docker/transition-monorepo.sh'), repository, 'target', backup, 'retained'], {
            env: { ...process.env, PATH: `${join(directory, 'bin')}:${process.env.PATH}`, TRACE: trace },
            encoding: 'utf8',
        });
        assert.equal(result.status, 0, result.stderr);
        assert.equal((await stat(join(repository, 'apps/finance/public'))).mode & 0o777, 0o777 & ~process.umask());
        assert.equal((await stat(join(repository, 'apps/finance/.env'))).mode & 0o777, 0o600);
        assert.equal((await stat(backup)).mode & 0o777, 0o700);
        assert.equal(await readFile(join(repository, 'apps/finance/storage/app/private/receipt'), 'utf8'), 'retained receipt');
        assert.equal(await readFile(join(backup, 'storage/app/private/receipt'), 'utf8'), 'retained receipt');
        assert.match(await readFile(join(repository, 'apps/finance/.env'), 'utf8'), /APP_KEY=retained-key\n\nCOMPOSE_PROJECT_NAME=retained/);
        assert.equal(await readFile(join(repository, 'apps/finance/public/build/manifest.json'), 'utf8'), '{"old":true}');
        const commands = await readFile(trace, 'utf8');
        assert.ok(commands.indexOf('php artisan down') < commands.indexOf('git checkout'));
        assert.ok(commands.indexOf('stop --timeout 90 nginx php') < commands.indexOf('git checkout'));
        assert.ok(commands.includes('new-deploy --no-pull'));
        assert.equal(commands.includes('down -v'), false);
    } finally {
        await rm(directory, { recursive: true, force: true });
    }
});

test('deployment refuses a different existing MySQL identity before touching services', async () => {
    const { status, trace } = await deploy({ mismatchedIdentity: true });
    assert.notEqual(status, 0);
    assert.equal(trace.includes('git pull'), false);
    assert.equal(trace.includes('php artisan down'), false);
    assert.equal(trace.includes('up -d'), false);
});


test('deployment refreshes the mirrored domain package before starting consumers', async () => {
    const { status, trace } = await deploy();
    assert.equal(status, 0);
    const sync = trace.indexOf('composer reinstall fin-si/finance-domain');
    assert.ok(sync > trace.indexOf('composer install --no-dev'));
    assert.ok(sync < trace.indexOf('app:post-deploy --migrate'));
});

test('enabled chat builds and migrates before restarting all consumers', async () => {
    const { status, trace } = await deploy({ chat: true, existingChat: true });
    assert.equal(status, 0);
    assert.ok(trace.indexOf('docker stop --time 130 chat-container') < trace.indexOf('git pull'));
    assert.ok(trace.indexOf('stop --timeout 90 nginx php') < trace.indexOf('git pull'));
    assert.ok(trace.includes('-f docker-compose.prod.yml -f docker-compose.chat.yml --profile chat'));
    assert.equal(trace.includes('docker-compose.chat.local.yml'), false);
    assert.equal(trace.includes('docker-compose.chat.host.yml'), false);
    assert.ok(trace.indexOf('build --pull agent-api mcp-php') < trace.indexOf('run --rm --no-deps agent-migrate'));
    assert.ok(trace.indexOf('app:post-deploy --migrate') < trace.indexOf('up -d --force-recreate --wait mcp-php agent-mcp agent-api'));
    assert.ok(trace.indexOf('up -d --wait agent-postgres') < trace.indexOf('run --rm --no-deps agent-migrate'));
    assert.ok(trace.indexOf('run --rm --no-deps agent-migrate') < trace.indexOf('up -d --force-recreate --wait mcp-php agent-mcp agent-api'));
    assert.ok(trace.indexOf('php scripts/check-agent.php') < trace.indexOf('up -d --force-recreate --wait agent-worker agent-maintenance'));
    assert.ok(trace.indexOf('up -d --force-recreate --wait agent-worker agent-maintenance') < trace.indexOf('php artisan up\n'));
    assert.equal(trace.includes('down -v'), false);
});

for (const fail of ['php scripts/check-agent.php --legal-hashes', 'run --rm --no-deps agent-migrate', 'php scripts/check-production.php', 'python -m finance_agent.deployment_check', 'php scripts/check-agent.php']) {
    test(`chat failure keeps traffic closed: ${fail}`, async () => {
        const { status, trace } = await deploy({ chat: true, existingChat: true, fail });
        assert.notEqual(status, 0);
        assert.equal(trace.includes('php artisan up\n'), false);
        assert.equal(trace.includes('up -d --force-recreate --wait agent-worker agent-maintenance'), false);
        assert.ok(trace.indexOf('docker stop --time 130 chat-container', trace.indexOf(fail)) > trace.indexOf(fail));
    });
}

test('disabled chat stops existing consumers without requiring or starting its stack', async () => {
    const { status, trace } = await deploy({ existingChat: true });
    assert.equal(status, 0);
    assert.ok(trace.includes('docker stop --time 130 chat-container'));
    assert.equal(trace.includes('-f docker-compose.chat.yml'), false);
    assert.equal(trace.includes('build --pull agent-api'), false);
});

test('preflight failure leaves running services untouched', async () => {
    const { status, trace } = await deploy({ fail: 'validate-production.mjs', existingChat: true });
    assert.notEqual(status, 0);
    assert.equal(trace.includes('php artisan down'), false);
    assert.equal(trace.includes('docker stop'), false);
    assert.equal(trace.includes('git pull'), false);
});

test('entrypoint changed by pull requires rerun without reopening traffic', async () => {
    const { status, trace } = await deploy({ changedEntrypoint: true });
    assert.notEqual(status, 0);
    assert.ok(trace.includes('git pull'));
    assert.equal(trace.includes('build --pull php'), false);
    assert.equal(trace.includes('php artisan up\n'), false);
});
