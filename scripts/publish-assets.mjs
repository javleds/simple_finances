import { access, copyFile, mkdir, readFile, readdir, rename, rm } from 'node:fs/promises';
import path from 'node:path';

const staged = path.resolve('storage/app/deploy-assets/build');
const published = path.resolve('public/build');
const manifestPath = path.join(staged, 'manifest.json');
const manifest = JSON.parse(await readFile(manifestPath, 'utf8'));
if (!manifest['resources/js/main.ts']?.isEntry)
    throw new Error('Build manifest is missing the Vue entrypoint');
for (const entry of Object.values(manifest)) {
    for (const filename of [entry.file, ...(entry.css ?? []), ...(entry.assets ?? [])]) {
        if (typeof filename !== 'string') throw new Error('Invalid build asset filename');
        const assetPath = path.resolve(staged, filename);
        if (!assetPath.startsWith(`${staged}${path.sep}`))
            throw new Error('Build asset outside staging directory');
        await access(assetPath);
    }
}

async function copyAssets(directory, relative = '') {
    for (const entry of await readdir(directory, { withFileTypes: true })) {
        const name = path.join(relative, entry.name);
        if (name === 'manifest.json') continue;
        if (!entry.isDirectory() && !entry.isFile())
            throw new Error(`Unexpected build entry: ${name}`);
        const destination = path.join(published, name);
        if (entry.isDirectory()) {
            await mkdir(destination, { recursive: true });
            await copyAssets(path.join(directory, entry.name), name);
        } else {
            await mkdir(path.dirname(destination), { recursive: true });
            await copyFile(path.join(directory, entry.name), destination);
        }
    }
}

await mkdir(published, { recursive: true });
await copyAssets(staged);
try {
    const currentManifest = await readFile(path.join(published, 'manifest.json'), 'utf8');
    if (currentManifest !== (await readFile(manifestPath, 'utf8'))) {
        await copyFile(
            path.join(published, 'manifest.json'),
            path.join(published, 'manifest.previous.json'),
        );
    }
} catch (error) {
    if (error.code !== 'ENOENT') throw error;
}
await copyFile(manifestPath, path.join(published, 'manifest.next.json'));
await rename(path.join(published, 'manifest.next.json'), path.join(published, 'manifest.json'));
await rm(path.resolve('public/hot'), { force: true });
