import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { once } from 'node:events';
import { copyFile, mkdir, mkdtemp, readFile, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { setTimeout as delay } from 'node:timers/promises';
import test from 'node:test';

async function fixture(t, services) {
    const root = await mkdtemp(join(tmpdir(), 'fin-si-dev-'));
    await mkdir(join(root, 'scripts'));
    await mkdir(join(root, 'apps'));
    await copyFile(new URL('./dev.mts', import.meta.url), join(root, 'scripts/dev.mts'));
    for (const [name, body] of Object.entries(services)) {
        await mkdir(join(root, 'apps', name, 'scripts'), { recursive: true });
        await writeFile(join(root, 'apps', name, 'scripts/dev.sh'), body);
    }
    t.after(() => rm(root, { recursive: true, force: true }));
    return root;
}

function launch(t, root, args = [], env = {}) {
    const child = spawn(process.execPath, [join(root, 'scripts/dev.mts'), ...args], {
        env: { ...process.env, SERVICES: '', NODE: process.execPath, ...env },
        stdio: ['ignore', 'pipe', 'pipe'],
    });
    let output = '';
    let stdout = '';
    child.stdout.on('data', (data) => {
        output += data;
        stdout += data;
    });
    child.stderr.on('data', (data) => {
        output += data;
    });
    const done = once(child, 'exit');
    t.after(() => {
        if (child.exitCode === null) child.kill('SIGTERM');
    });
    return { child, done, output: () => output, stdout: () => stdout };
}

async function waitFor(path) {
    for (let attempt = 0; attempt < 100; attempt++) {
        try {
            return await readFile(path, 'utf8');
        } catch {
            await delay(30);
        }
    }
    throw new Error(`Timed out waiting for ${path}`);
}

test('lists available applications without starting them', async (t) => {
    const root = await fixture(t, { finance: 'exit 17', agents: 'exit 18' });
    const run = launch(t, root, ['--list']);
    assert.equal((await run.done)[0], 0);
    assert.equal(run.stdout(), 'agents\nfinance\n');
});

test('validates every selection before starting any process', async (t) => {
    const root = await fixture(t, { finance: 'echo should-not-start' });
    const run = launch(t, root, ['finance', '../missing']);
    assert.equal((await run.done)[0], 1);
    assert.match(run.output(), /Unknown service/);
    assert.doesNotMatch(run.output(), /should-not-start|Starting finance/);
});

test('starts only the selected application once and preserves failure status', async (t) => {
    const root = await fixture(t, { finance: 'exit 7', agents: 'echo should-not-start' });
    const run = launch(t, root, [], { SERVICES: 'finance finance' });
    assert.equal((await run.done)[0], 7);
    assert.equal(run.output().match(/Starting finance/g).length, 1);
    assert.doesNotMatch(run.output(), /should-not-start/);
});

test('stops a sibling when a service exits', async (t) => {
    const root = await fixture(t, {
        agents: 'exec "$NODE" -e \'const fs=require("fs"); fs.writeFileSync("ready", "yes"); process.on("SIGTERM",()=>{fs.writeFileSync("stopped","yes");process.exit(0)});setInterval(()=>{},100)\'',
        finance: 'while [ ! -f ../agents/ready ]; do sleep 0.03; done; exit 9',
    });
    const run = launch(t, root);
    assert.equal((await run.done)[0], 9);
    assert.equal(await readFile(join(root, 'apps/agents/stopped'), 'utf8'), 'yes');
});

test('Ctrl+C terminates child process groups', async (t) => {
    const root = await fixture(t, {
        finance:
            '"$NODE" -e \'const fs=require("fs");fs.writeFileSync("pid",String(process.pid));setInterval(()=>{},100)\' &\nwait',
    });
    const run = launch(t, root);
    const pid = Number(await waitFor(join(root, 'apps/finance/pid')));
    run.child.kill('SIGINT');
    assert.equal((await run.done)[0], 130);
    assert.throws(() => process.kill(pid, 0), { code: 'ESRCH' });
});

test('reports an empty service inventory', async (t) => {
    const root = await fixture(t, {});
    const run = launch(t, root);
    assert.equal((await run.done)[0], 1);
    assert.match(run.output(), /No development services/);
});

test('forces shutdown when a service ignores SIGTERM', async (t) => {
    const root = await fixture(t, {
        finance:
            'exec "$NODE" -e \'const fs=require("fs");process.on("SIGTERM",()=>{});fs.writeFileSync("pid",String(process.pid));setInterval(()=>{},100)\'',
    });
    const run = launch(t, root);
    const pid = Number(await waitFor(join(root, 'apps/finance/pid')));
    run.child.kill('SIGINT');
    assert.equal((await run.done)[0], 130);
    for (let attempt = 0; attempt < 50; attempt++) {
        try {
            process.kill(pid, 0);
        } catch (error) {
            assert.equal(error.code, 'ESRCH');
            return;
        }
        await delay(20);
    }
    assert.fail('Service survived forced shutdown');
});
