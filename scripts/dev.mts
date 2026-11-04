import { spawn } from 'node:child_process';
import { readdir, stat } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');

async function discoverServices(): Promise<Map<string, string>> {
    const services = new Map<string, string>();
    const entries = await readdir(join(root, 'apps'), { withFileTypes: true });
    for (const entry of entries.sort((left, right) => left.name.localeCompare(right.name))) {
        if (!entry.isDirectory() || !/^[a-z][a-z0-9-]*$/.test(entry.name)) continue;
        const script = join(root, 'apps', entry.name, 'scripts', 'dev.sh');
        try {
            if ((await stat(script)).isFile()) services.set(entry.name, script);
        } catch (error) {
            if ((error as NodeJS.ErrnoException).code !== 'ENOENT') throw error;
        }
    }
    return services;
}

function signalGroup(pid: number, signal: NodeJS.Signals | 0): boolean {
    try {
        process.kill(-pid, signal);
        return true;
    } catch (error) {
        if ((error as NodeJS.ErrnoException).code !== 'ESRCH') throw error;
        return false;
    }
}

function supervise(services: Map<string, string>): void {
    const groups: number[] = [];
    let stopping = false;

    const stop = (code: number): void => {
        if (stopping) return;
        stopping = true;
        for (const pid of groups) signalGroup(pid, 'SIGTERM');
        const deadline = setTimeout(() => {
            for (const pid of groups) signalGroup(pid, 'SIGKILL');
            process.exit(code);
        }, 5000);
        const check = setInterval(() => {
            if (groups.some((pid) => signalGroup(pid, 0))) return;
            clearInterval(check);
            clearTimeout(deadline);
            process.exit(code);
        }, 50);
    };

    process.on('SIGINT', () => stop(130));
    process.on('SIGTERM', () => stop(143));

    for (const [name, script] of services) {
        console.log(`[dev] Starting ${name}`);
        const child = spawn('bash', [script], {
            cwd: join(root, 'apps', name),
            stdio: 'inherit',
            detached: true,
        });
        if (child.pid !== undefined) groups.push(child.pid);
        child.once('error', (error: Error) => {
            console.error(`[dev] ${name}: ${error.message}`);
            stop(1);
        });
        child.once('exit', (code: number | null, signal: NodeJS.Signals | null) => {
            if (!stopping)
                console.log(`[dev] ${name} stopped (${signal ?? code}); stopping services.`);
            stop(code ?? 1);
        });
    }
}

async function main(): Promise<void> {
    if (process.platform === 'win32')
        throw new Error('Use macOS, Linux or WSL to run development services.');
    const services = await discoverServices();
    const args = process.argv.slice(2);
    if (args.length === 1 && args[0] === '--list') {
        console.log([...services.keys()].join('\n'));
        return;
    }
    const requested =
        args.length > 0 ? args : (process.env.SERVICES ?? '').trim().split(/\s+/).filter(Boolean);
    const names = requested.length > 0 ? [...new Set(requested)] : [...services.keys()];
    if (names.length === 0)
        throw new Error('No development services found in apps/*/scripts/dev.sh.');
    for (const name of names) {
        if (!services.has(name))
            throw new Error(
                `Unknown service: ${name}. Available: ${[...services.keys()].join(', ')}`,
            );
    }
    supervise(new Map(names.map((name) => [name, services.get(name)!])));
}

main().catch((error: unknown) => {
    console.error(`[dev] ${error instanceof Error ? error.message : String(error)}`);
    process.exitCode = 1;
});
