import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { copyFileSync, mkdirSync, mkdtempSync, readFileSync, realpathSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';

for (const scenario of ['finance', 'all', 'finance-failure', 'chat-failure']) {
    test(`Local migrations: ${scenario}`, t => {
        const root = realpathSync(mkdtempSync(join(tmpdir(), 'finance-migrations-')));
        t.after(() => rmSync(root, { recursive: true, force: true }));
        mkdirSync(join(root, 'apps/finance/scripts'), { recursive: true });
        mkdirSync(join(root, 'bin'));
        copyFileSync(new URL('../Makefile', import.meta.url), join(root, 'Makefile'));
        copyFileSync(new URL('../apps/finance/scripts/migrate.sh', import.meta.url), join(root, 'apps/finance/scripts/migrate.sh'));
        const log = join(root, 'calls');
        for (const [name, body] of Object.entries({
            uname: 'echo Linux',
            php: `printf 'php %s %s\\n' "$PWD" "$*" >> "$MIGRATION_LOG"\nexit ${scenario === 'finance-failure' ? 7 : 0}`,
            docker: `printf 'docker %s\\n' "$*" >> "$MIGRATION_LOG"\nexit ${scenario === 'chat-failure' ? 8 : 0}`,
        })) writeFileSync(join(root, 'bin', name), `#!/bin/sh\n${body}\n`, { mode: 0o755 });
        const result = spawnSync('make', ['-j4', scenario === 'finance' ? 'migrate-finances' : 'migrate'], {
            cwd: root,
            env: { ...process.env, PATH: `${join(root, 'bin')}:/usr/bin:/bin`, MIGRATION_LOG: log },
            encoding: 'utf8',
        });
        assert.equal(result.status === 0, !scenario.endsWith('failure'), result.stderr);
        const calls = readFileSync(log, 'utf8').trim().split('\n');
        assert.equal(calls[0], `php ${root}/apps/finance artisan migrate`);
        if (scenario === 'finance' || scenario === 'finance-failure') {
            assert.equal(calls.length, 1);
            return;
        }
        assert.equal(calls.length, scenario === 'chat-failure' ? 2 : 4);
        assert.ok(calls[1].endsWith('build agent-migrate'));
        if (scenario === 'all') {
            assert.ok(calls[2].endsWith('up -d --wait agent-postgres'));
            assert.ok(calls[3].endsWith('run --rm --no-deps agent-migrate'));
        }
    });
}
