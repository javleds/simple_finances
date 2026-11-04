import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { copyFileSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';

for (const failure of [false, true]) {
    test(`Finance sync ${failure ? 'failure prevents startup' : 'precedes startup'}`, t => {
        const root = mkdtempSync(join(tmpdir(), 'finance-dev-'));
        t.after(() => rmSync(root, { recursive: true, force: true }));
        mkdirSync(join(root, 'scripts'));
        mkdirSync(join(root, 'bin'));
        copyFileSync(new URL('../apps/finance/scripts/dev.sh', import.meta.url), join(root, 'scripts/dev.sh'));
        for (const [name, body] of Object.entries({
            uname: 'echo Linux',
            php: 'exit 0',
            composer: `echo "composer $*" >> "$START_LOG"\nexit ${failure ? 17 : 0}`,
            npx: 'echo npx >> "$START_LOG"',
        })) writeFileSync(join(root, 'bin', name), `#!/bin/sh\n${body}\n`, { mode: 0o755 });
        const run = spawnSync('/bin/bash', [join(root, 'scripts/dev.sh')], {
            env: { ...process.env, PATH: `${join(root, 'bin')}:/usr/bin:/bin`, START_LOG: join(root, 'startup.log') },
            encoding: 'utf8',
        });
        assert.equal(run.status, failure ? 17 : 0, run.stderr);
        assert.equal(readFileSync(join(root, 'startup.log'), 'utf8'),
            failure ? 'composer run domain:sync\n' : 'composer run domain:sync\nnpx\n');
    });
}
