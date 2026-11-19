import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, copyFileSync, rmSync, existsSync, statSync, symlinkSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawnSync } from 'node:child_process';
import { gunzipSync } from 'node:zlib';
import test from 'node:test';

function fixture(t) {
    const root = mkdtempSync(join(tmpdir(), 'fin-si-transition-'));
    t.after(() => rmSync(root, { recursive: true, force: true }));
    const apps = join(root, 'apps');
    const repo = join(apps, 'simple_finances');
    const backup = join(apps, 'backup');
    const trace = join(root, 'trace');
    mkdirSync(repo, { recursive: true });
    mkdirSync(join(apps, 'traefik'));
    writeFileSync(join(apps, 'traefik', 'config.yml'), 'retained proxy configuration');
    const write = (file, value) => {
        const path = join(repo, file);
        mkdirSync(resolve(path, '..'), { recursive: true });
        writeFileSync(path, value);
    };
    const git = (...args) => {
        const result = spawnSync('git', args, { cwd: repo, encoding: 'utf8' });
        assert.equal(result.status, 0, result.stderr);
        return result.stdout.trim();
    };
    git('init', '-b', 'legacy');
    git('config', 'user.email', 'test@example.invalid');
    git('config', 'user.name', 'Transition Test');
    write('.gitignore', '.env\napps/*/.env\n/storage/*\n!/storage/.gitignore\n/public/build\n/vendor\n/node_modules\n');
    write('artisan', 'legacy');
    write('storage/.gitignore', '*\n!.gitignore\n');
    write('docker-compose.prod.yml', 'services: {}\n');
    git('add', '.'); git('commit', '-m', 'test: legacy');
    const previous = git('rev-parse', 'HEAD');
    git('rm', 'artisan', 'storage/.gitignore');
    write('apps/finance/artisan', 'monorepo');
    write('packages/finance-domain/composer.json', '{}');
    write('apps/mcp/.env.example', 'APP_ENV=local\nAPP_KEY=\n');
    write('apps/agents/.env.example', 'OPENROUTER_API_KEY=\n');
    write('scripts/validate-production.mjs', '');
    write('deploy.sh', '#!/usr/bin/env bash\nprintf "deploy %s\\n" "$*" >> "$TRACE"\n[[ "$FAIL_DEPLOY" != 1 ]]\n');
    git('add', '.'); git('commit', '-m', 'test: monorepo');
    const target = git('rev-parse', 'HEAD');
    git('checkout', '--detach', previous);
    write('.env', 'APP_KEY=original-key\nAPP_ENV=production\nAPP_DEBUG=false\nDB_CONNECTION=mysql\nDB_HOST=mysql\nDB_DATABASE=finance\nDB_PASSWORD="original password"\nTRAEFIK_APP_NAME=existing-router\nAPP_DOMAIN=fin-si.com\nAPI_DOMAIN=api.finsi.com\nAPP_URL=https://fin-si.com\nSPA_URL=https://fin-si.com\nFRONTEND_URL=https://fin-si.com\n');
    write('storage/app/private/receipt', 'retained receipt');
    write('public/build/manifest.json', '{"old":true}');
    mkdirSync(join(root, 'bin'));
    writeFileSync(join(root, 'bin/flock'), '#!/usr/bin/env bash\nexit 0\n', { mode: 0o755 });
    writeFileSync(join(root, 'bin/docker'), `#!/usr/bin/env bash
printf 'docker %s\\n' "$*" >> "$TRACE"
if [[ "$1" == inspect ]]; then
  if [[ "$3" == *Mounts* ]]; then echo retained_simplefinancesdb; elif [[ "$3" == *Config.Env* ]]; then echo MYSQL_DATABASE=finance; else echo retained; fi
fi
if [[ "$*" == *'config --services'* ]]; then printf 'php\\nnginx\\nmysql\\nattachments-worker\\nscheduler\\n'; fi
if [[ "$*" == *mysqldump* ]]; then
  if [[ "$FAIL_DUMP" == 1 ]]; then exit 1; fi
  if [[ "$EMPTY_DUMP" != 1 ]]; then printf '%s\\n' '-- fake mysql backup'; fi
fi
`, { mode: 0o755 });
    const script = join(root, 'transition.sh');
    copyFileSync(new URL('../docker/transition-monorepo.sh', import.meta.url), script);
    const run = (args = [], env = {}) => spawnSync('bash', [script, '--repo', repo, '--target', target, '--backup', backup, ...args], {
        cwd: root, encoding: 'utf8', env: { ...process.env, PATH: `${join(root, 'bin')}:${process.env.PATH}`, TRACE: trace, FAIL_DEPLOY: '0', FAIL_DUMP: '0', ...env },
    });
    return { root, apps, repo, backup, trace, run, previous, target, git, write };
}

test('default check detects Compose project and changes no files or services', t => {
    const f = fixture(t);
    const result = f.run();
    assert.equal(result.status, 0, result.stderr);
    assert.match(result.stdout, /Project: retained/);
    assert.equal(f.git('rev-parse', 'HEAD'), f.previous);
    assert.equal(existsSync(f.backup), false);
    assert.equal(readFileSync(f.trace, 'utf8').includes('artisan down'), false);
    assert.equal(readFileSync(join(f.repo, 'artisan'), 'utf8'), 'legacy');
});

test('apply retains data and configuration, backs up MySQL and leaves Traefik intact', t => {
    const f = fixture(t);
    const result = f.run(['--apply']);
    assert.equal(result.status, 0, result.stderr);
    assert.equal(f.git('rev-parse', 'HEAD'), f.target);
    assert.equal(gunzipSync(readFileSync(join(f.backup, 'mysql.sql.gz'))).toString(), '-- fake mysql backup\n');
    assert.equal(readFileSync(join(f.backup, 'storage/app/private/receipt'), 'utf8'), 'retained receipt');
    assert.equal(readFileSync(join(f.repo, 'apps/finance/storage/app/private/receipt'), 'utf8'), 'retained receipt');
    assert.match(readFileSync(join(f.repo, 'apps/finance/.env'), 'utf8'), /APP_KEY=original-key/);
    assert.match(readFileSync(join(f.repo, 'apps/finance/.env'), 'utf8'), /COMPOSE_PROJECT_NAME=retained\nCHAT_ENABLED=false/);
    assert.equal(readFileSync(join(f.repo, '.env'), 'utf8').includes('APP_KEY'), false);
    assert.match(readFileSync(join(f.repo, '.env'), 'utf8'), /DB_PASSWORD="original password"/);
    for (const file of ['.env', 'apps/finance/.env', 'apps/mcp/.env', 'apps/agents/.env']) assert.equal(statSync(join(f.repo, file)).mode & 0o777, 0o600);
    assert.equal(statSync(f.backup).mode & 0o777, 0o700);
    assert.equal(readFileSync(join(f.backup, 'stage'), 'utf8'), 'complete\n');
    assert.equal(readFileSync(join(f.apps, 'traefik/config.yml'), 'utf8'), 'retained proxy configuration');
    const trace = readFileSync(f.trace, 'utf8');
    assert.ok(trace.indexOf('stop --timeout 90 scheduler') < trace.indexOf('mysqldump'));
    assert.ok(trace.indexOf('stop --timeout 90 nginx php') < trace.indexOf('mysqldump'));
    assert.ok(trace.indexOf('mysqldump') < trace.indexOf('deploy --no-pull'));
    assert.equal(trace.includes('down -v'), false);
    assert.equal(trace.includes('stop --timeout 90 mysql'), false);
});

for (const env of [{ FAIL_DUMP: '1' }, { EMPTY_DUMP: '1' }]) {
    test(`dump failure preserves original checkout and storage: ${JSON.stringify(env)}`, t => {
        const f = fixture(t);
        const result = f.run(['--apply'], env);
        assert.notEqual(result.status, 0);
        assert.equal(f.git('rev-parse', 'HEAD'), f.previous);
        assert.equal(readFileSync(join(f.repo, 'storage/app/private/receipt'), 'utf8'), 'retained receipt');
        assert.equal(readFileSync(join(f.backup, 'stage'), 'utf8'), 'mysql-backup\n');
        assert.equal(readFileSync(f.trace, 'utf8').includes('deploy --no-pull'), false);
    });
}

test('deployment failure retains backup and stage without restoring stale data automatically', t => {
    const f = fixture(t);
    const result = f.run(['--apply'], { FAIL_DEPLOY: '1' });
    assert.notEqual(result.status, 0);
    assert.equal(f.git('rev-parse', 'HEAD'), f.target);
    assert.equal(readFileSync(join(f.backup, 'stage'), 'utf8'), 'deploy\n');
    assert.ok(existsSync(join(f.backup, 'mysql.sql.gz')));
});

test('rejects backup symlink into checkout before maintenance', t => {
    const f = fixture(t);
    symlinkSync(f.repo, join(f.root, 'alias'));
    const result = f.run(['--backup', join(f.root, 'alias/backup'), '--apply']);
    assert.notEqual(result.status, 0);
    assert.match(result.stderr, /outside the checkout/);
    assert.equal(existsSync(f.trace), false);
});

test('rejects missing domains, preexisting app env, wrong project and repeat migration', t => {
    const f = fixture(t);
    let result = f.run(['--project', 'wrong', '--apply']);
    assert.notEqual(result.status, 0);
    assert.equal(readFileSync(f.trace, 'utf8').includes('artisan down'), false);
    f.write('apps/mcp/.env', 'preserve-me');
    result = f.run(['--apply']);
    assert.notEqual(result.status, 0);
    assert.equal(readFileSync(join(f.repo, 'apps/mcp/.env'), 'utf8'), 'preserve-me');
    rmSync(join(f.repo, 'apps'), { recursive: true });
    const original = readFileSync(join(f.repo, '.env'), 'utf8');
    f.write('.env', original.replace('FRONTEND_URL=https://fin-si.com', ''));
    assert.match(f.run().stderr, /FRONTEND_URL/);
    f.write('.env', original);
    assert.equal(f.run(['--apply']).status, 0);
    result = f.run(['--apply', '--backup', join(f.apps, 'another-backup')]);
    assert.notEqual(result.status, 0);
    assert.match(result.stderr, /original root Laravel layout/);
});


test('rejects symlinked environment without overwriting external secrets', t => {
    const f = fixture(t);
    const external = join(f.root, 'external.env');
    const original = readFileSync(join(f.repo, '.env'), 'utf8');
    writeFileSync(external, original);
    rmSync(join(f.repo, '.env'));
    symlinkSync(external, join(f.repo, '.env'));
    const result = f.run(['--apply']);
    assert.notEqual(result.status, 0);
    assert.equal(readFileSync(external, 'utf8'), original);
    assert.equal(existsSync(f.trace), false);
    assert.equal(existsSync(f.backup), false);
});

for (const [key, value] of [['DB_HOST', 'external-mysql'], ['DB_DATABASE', 'another_database'], ['APP_ENV', 'local']]) {
    test(`rejects unsafe source configuration ${key} before stopping services`, t => {
        const f = fixture(t);
        const current = readFileSync(join(f.repo, '.env'), 'utf8');
        f.write('.env', current.replace(new RegExp(`^${key}=.*$`, 'm'), `${key}=${value}`));
        assert.notEqual(f.run(['--apply']).status, 0);
        assert.equal(existsSync(f.backup), false);
        if (existsSync(f.trace)) assert.equal(readFileSync(f.trace, 'utf8').includes('artisan down'), false);
    });
}

test('checkout failure leaves original revision and recovery files without starting deployment', t => {
    const f = fixture(t);
    const realGit = spawnSync('sh', ['-c', 'command -v git'], { encoding: 'utf8' }).stdout.trim();
    writeFileSync(join(f.root, 'bin/git'), '#!/usr/bin/env bash\nif [[ "$1" == checkout ]]; then exit 1; fi\nexec "$REAL_GIT" "$@"\n', { mode: 0o755 });
    const result = f.run(['--apply'], { REAL_GIT: realGit });
    assert.notEqual(result.status, 0);
    assert.equal(f.git('rev-parse', 'HEAD'), f.previous);
    assert.equal(readFileSync(join(f.backup, 'stage'), 'utf8'), 'checkout\n');
    assert.equal(readFileSync(join(f.backup, 'storage/app/private/receipt'), 'utf8'), 'retained receipt');
    assert.ok(existsSync(join(f.backup, 'mysql.sql.gz')));
    assert.equal(readFileSync(f.trace, 'utf8').includes('deploy --no-pull'), false);
});

test('rejects a target without the new deployment files before maintenance', t => {
    const f = fixture(t);
    assert.notEqual(f.run(['--apply', '--target', f.previous]).status, 0);
    assert.equal(existsSync(f.trace), false);
    assert.equal(existsSync(f.backup), false);
    assert.equal(f.git('rev-parse', 'HEAD'), f.previous);
});
