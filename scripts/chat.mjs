import { existsSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { configure, readSettings } from './chat-config.mjs';

const root = fileURLToPath(new URL('../', import.meta.url));
const envPath = `${root}.env`;
const action = process.argv[2];
const compose = ['compose', '--env-file', envPath, '-f', 'docker-compose.yml', '-f', 'docker-compose.chat.yml', '-f', 'docker-compose.chat.local.yml', '--profile', 'chat'];

function run(args, input) {
    const result = spawnSync('docker', args, { cwd: root, stdio: input ? ['pipe', 'inherit', 'inherit'] : 'inherit', input });
    if (result.error) throw result.error;
    if (result.status !== 0) throw new Error(`Docker exited with status ${result.status}`);
}

try {
    if (action === 'config') {
        configure(root);
        console.log('Prepared per-application environments. Configure each app as documented in docs/operations/assistant.md.');
        process.exit(0);
    }
    const env = readSettings(root, '.env');
    const mcp = readSettings(root, 'apps/mcp/.env');
    const agent = readSettings(root, 'apps/agents/.env');
    if (action === 'setup') {
        if (!/^[a-zA-Z0-9_]+$/.test(mcp.DB_DATABASE ?? '') || !/^[a-f0-9]{64}$/.test(mcp.DB_PASSWORD ?? '')) {
            throw new Error('Setup requires an alphanumeric DB_DATABASE and the generated 64-character hex DB_PASSWORD in apps/mcp/.env.');
        }
        if (mcp.DB_USERNAME !== 'finance_mcp' || mcp.DB_HOST !== 'mysql' || mcp.DB_DATABASE !== env.DB_DATABASE) throw new Error('Local setup requires MCP to use finance_mcp on mysql and the infrastructure DB_DATABASE.');
        run([...compose, 'build', 'php', 'mcp-php', 'agent-api']);
        run([...compose, 'up', '-d', '--wait', 'mysql', 'agent-postgres']);
        const sql = `CREATE USER IF NOT EXISTS 'finance_mcp'@'%' IDENTIFIED BY '${mcp.DB_PASSWORD}';\nALTER USER 'finance_mcp'@'%' IDENTIFIED BY '${mcp.DB_PASSWORD}';\nREVOKE ALL PRIVILEGES, GRANT OPTION FROM 'finance_mcp'@'%';\nGRANT SELECT ON \`${mcp.DB_DATABASE}\`.* TO 'finance_mcp'@'%';\n`;
        run([...compose, 'exec', '-T', 'mysql', 'sh', '-c', 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -u root'], sql);
        run([...compose, 'run', '--rm', '--no-deps', 'agent-migrate']);
        console.log('Chat infrastructure initialized. Finance schema/data are unchanged; see docs/operations/assistant.md.');
        process.exit(0);
    }
    if (action === 'up') {
        for (const [file, values, keys] of [
            ['apps/agents/.env', agent, ['OPENROUTER_API_KEY', 'AGENT_MODEL', 'DATABASE_URL']],
            ['apps/mcp/.env', mcp, ['MCP_LEGAL_TERMS_HASH', 'MCP_LEGAL_PRIVACY_HASH']],
        ]) {
            for (const key of keys) if (!values[key]) throw new Error(`Configure ${key} in ${file} before starting the assistant.`);
        }
        const finance = readSettings(root, 'apps/finance/.env');
        for (const [left, right, key] of [
            [finance, agent, 'FINANCE_AGENT_SERVICE_TOKEN'], [agent, mcp, 'AGENT_MCP_SERVICE_TOKEN'],
        ]) {
            if (!left[key] || left[key] !== right[key]) throw new Error(`Configure matching ${key} in its two application environments.`);
        }
        if (existsSync(`${root}apps/finance/public/hot`)) throw new Error('Stop the host Vite server and remove its stale public/hot file before using the compiled Docker stack.');
        if (!existsSync(`${root}apps/finance/public/build/manifest.json`)) throw new Error('Run npm run build in apps/finance first.');
        run([...compose, 'up', '--build', 'nginx', 'php', 'mysql', 'agent-postgres', 'agent-api', 'agent-worker', 'agent-maintenance', 'mcp-php', 'agent-mcp']);
        process.exit(0);
    }
    if (action === 'down') {
        run([...compose, 'down']);
        process.exit(0);
    }
    throw new Error('Usage: node scripts/chat.mjs config|setup|up|down');
} catch (error) {
    console.error(error instanceof Error ? error.message : String(error));
    process.exitCode = 1;
}
