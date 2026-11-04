import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdtemp, mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import test from 'node:test';

const publisher = fileURLToPath(new URL('./publish-assets.mjs', import.meta.url));

async function fixture(run) {
    const root = await mkdtemp(path.join(tmpdir(), 'fin-si-assets-'));
    try {
        await mkdir(path.join(root, 'storage/app/deploy-assets/build/assets'), { recursive: true });
        await mkdir(path.join(root, 'public/build/assets'), { recursive: true });
        await writeFile(path.join(root, 'public/build/assets/old.js'), 'old');
        await writeFile(path.join(root, 'public/build/manifest.json'), '{"old":true}');
        await writeFile(path.join(root, 'public/hot'), 'http://127.0.0.1:5173');
        await writeFile(path.join(root, 'storage/app/deploy-assets/build/assets/new.js'), 'new');
        await run(root);
    } finally {
        await rm(root, { recursive: true, force: true });
    }
}

const manifest = JSON.stringify({
    'resources/js/main.ts': { isEntry: true, file: 'assets/new.js' },
});
const publish = (cwd) => execFileSync(process.execPath, [publisher], { cwd, stdio: 'pipe' });

await test('publishes new assets, retains old assets and a rollback manifest, removes hot marker', async () => {
    await fixture(async (root) => {
        await writeFile(path.join(root, 'storage/app/deploy-assets/build/manifest.json'), manifest);
        publish(root);
        assert.equal(
            await readFile(path.join(root, 'public/build/manifest.json'), 'utf8'),
            manifest,
        );
        assert.equal(
            await readFile(path.join(root, 'public/build/manifest.previous.json'), 'utf8'),
            '{"old":true}',
        );
        assert.equal(await readFile(path.join(root, 'public/build/assets/old.js'), 'utf8'), 'old');
        assert.equal(await readFile(path.join(root, 'public/build/assets/new.js'), 'utf8'), 'new');
        await assert.rejects(readFile(path.join(root, 'public/hot')), { code: 'ENOENT' });
        publish(root);
        assert.equal(
            await readFile(path.join(root, 'public/build/manifest.previous.json'), 'utf8'),
            '{"old":true}',
        );
    });
});

await test('refuses an incomplete build without replacing the live manifest', async () => {
    await fixture(async (root) => {
        const broken = JSON.stringify({
            'resources/js/main.ts': { isEntry: true, file: 'assets/missing.js' },
        });
        await writeFile(path.join(root, 'storage/app/deploy-assets/build/manifest.json'), broken);
        assert.throws(() => publish(root));
        assert.equal(
            await readFile(path.join(root, 'public/build/manifest.json'), 'utf8'),
            '{"old":true}',
        );
    });
});

await test('refuses manifests referencing files outside the build directory', async () => {
    await fixture(async (root) => {
        const broken = JSON.stringify({
            'resources/js/main.ts': { isEntry: true, file: '../secret' },
        });
        await writeFile(path.join(root, 'storage/app/deploy-assets/build/manifest.json'), broken);
        assert.throws(() => publish(root));
        assert.equal(
            await readFile(path.join(root, 'public/build/manifest.json'), 'utf8'),
            '{"old":true}',
        );
    });
});
