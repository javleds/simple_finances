import { realpathSync } from 'node:fs';
import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { readSettings } from './chat-config.mjs';

export function developmentEnvironment(finance, agent, mcp) {
    for (const [left, right, key] of [
        [finance, agent, 'FINANCE_AGENT_SERVICE_TOKEN'],
        [agent, mcp, 'AGENT_MCP_SERVICE_TOKEN'],
    ]) {
        if (!left[key] || left[key] !== right[key]) throw new Error(`Configure matching ${key} in application environments.`);
    }
    if (finance.DB_CONNECTION !== 'mysql' || !finance.DB_HOST || !finance.DB_DATABASE)
        throw new Error('Host chat development requires Finance DB_CONNECTION=mysql, DB_HOST and DB_DATABASE.');
    if (!mcp.DB_USERNAME || !mcp.DB_PASSWORD || !mcp.MCP_LEGAL_TERMS_HASH || !mcp.MCP_LEGAL_PRIVACY_HASH)
        throw new Error('Configure MCP read-only database credentials and legal hashes in apps/mcp/.env.');
    return {
        DEV_FINANCE_DB_HOST: ['127.0.0.1', 'localhost', '::1'].includes(finance.DB_HOST) ? 'host.docker.internal' : finance.DB_HOST,
        DEV_FINANCE_DB_PORT: finance.DB_PORT || '3306',
        DEV_FINANCE_DB_DATABASE: finance.DB_DATABASE,
    };
}

export async function main() {
    const root = fileURLToPath(new URL('../', import.meta.url));
    const env = { ...process.env, ...developmentEnvironment(
        readSettings(root, 'apps/finance/.env'),
        readSettings(root, 'apps/agents/.env'),
        readSettings(root, 'apps/mcp/.env'),
    ) };
    const compose = ['compose', '--env-file', '.env', '-f', 'docker-compose.yml', '-f', 'docker-compose.chat.yml', '-f', 'docker-compose.chat.host.yml', '--profile', 'chat'];
    const services = ['agent-api', 'agent-worker', 'agent-mcp', 'mcp-php'];
    const children = new Set();
    let stopping = false;
    function run(command, args, options = {}) {
        return new Promise((resolve, reject) => {
            const child = spawn(command, args, { cwd: root, env, stdio: 'inherit', ...options });
            children.add(child);
            child.once('error', reject);
            child.once('exit', (code, signal) => {
                children.delete(child);
                if (code === 0) resolve();
                else reject(new Error(`${command} stopped (${signal ?? code})`));
            });
        });
    }
    async function stop(code) {
        if (stopping) return;
        stopping = true;
        for (const child of children) child.kill('SIGTERM');
        try { await run('docker', [...compose, 'stop', '-t', '2', ...services]); }
        catch { process.exitCode = 1; }
        process.exitCode = code;
    }
    process.once('SIGINT', () => void stop(130));
    process.once('SIGTERM', () => void stop(143));
    try {
        await run('docker', [...compose, 'build', 'agent-api', 'mcp-php']);
        if (stopping) return;
        await run('docker', [...compose, 'up', '-d', '--wait', 'agent-postgres']);
        if (stopping) return;
        console.log('FastAPI: http://127.0.0.1:8001; MCP: http://127.0.0.1:8002/mcp; Studio: http://127.0.0.1:2024');
        await Promise.race([
            run('docker', [...compose, 'up', '--abort-on-container-exit', ...services]),
            run('uv', ['run', '--frozen', 'langgraph', 'dev', '--host', '127.0.0.1', '--port', '2024', '--no-browser'], {
                cwd: `${root}apps/agents`, env: { ...env, MCP_URL: 'http://127.0.0.1:8002/mcp' },
            }),
        ]);
        await stop(1);
    } catch (error) {
        if (!stopping) {
            console.error(error.message);
            await stop(1);
        }
    }
}

if (process.argv[1] && realpathSync(process.argv[1]) === fileURLToPath(import.meta.url)) {
    main().catch(error => { console.error(error.message); process.exitCode = 1; });
}
