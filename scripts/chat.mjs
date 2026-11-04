import { randomBytes } from 'node:crypto';
import { existsSync, readFileSync, writeFileSync, chmodSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../', import.meta.url));
const envPath = `${root}.env.chat`;
const action = process.argv[2];
const compose = ['compose', '--env-file', envPath, '-f', 'docker-compose.yml', '-f', 'docker-compose.chat.yml', '-f', 'docker-compose.chat.local.yml', '--profile', 'chat'];

function run(args, input) {
    const result = spawnSync('docker', args, { cwd: root, stdio: input ? ['pipe', 'inherit', 'inherit'] : 'inherit', input });
    if (result.error) throw result.error;
    if (result.status !== 0) throw new Error(`Docker exited with status ${result.status}`);
}

function settings() {
    if (!existsSync(envPath)) throw new Error('Run make chat-config first.');
    return Object.fromEntries(readFileSync(envPath, 'utf8').split('\n').filter(line => /^[A-Z_]+=/.test(line)).map(line => {
        const split = line.indexOf('=');
        return [line.slice(0, split), line.slice(split + 1)];
    }));
}

function configure() {
    if (existsSync(envPath)) throw new Error('.env.chat already exists; edit it without replacing existing passwords.');
    let content = readFileSync(`${root}.env.chat.example`, 'utf8');
    for (const name of ['DB_PASSWORD', 'AGENT_DATABASE_OWNER_PASSWORD', 'AGENT_DATABASE_PASSWORD', 'MCP_DATABASE_PASSWORD', 'FINANCE_AGENT_SERVICE_TOKEN', 'AGENT_MCP_SERVICE_TOKEN']) {
        content = content.replace(`${name}=\n`, `${name}=${randomBytes(32).toString('hex')}\n`);
    }
    content = content.replace('MCP_APP_KEY=\n', `MCP_APP_KEY=base64:${randomBytes(32).toString('base64')}\n`);
    writeFileSync(envPath, content, { flag: 'wx', mode: 0o600 });
    chmodSync(envPath, 0o600);
    console.log('Created .env.chat. Configure model/provider and legal hashes before enabling chat.');
}

try {
    if (action === 'config') {
        configure();
        process.exit(0);
    }
    const env = settings();
    if (action === 'setup') {
        if (!/^[a-zA-Z0-9_]+$/.test(env.DB_DATABASE ?? '') || !/^[a-f0-9]{64}$/.test(env.MCP_DATABASE_PASSWORD ?? '')) {
            throw new Error('Setup requires an alphanumeric DB_DATABASE and the generated 64-character hex MCP_DATABASE_PASSWORD.');
        }
        run([...compose, 'build', 'php', 'mcp-php', 'agent-api']);
        run([...compose, 'up', '-d', '--wait', 'mysql', 'agent-postgres']);
        const sql = `CREATE USER IF NOT EXISTS 'finance_mcp'@'%' IDENTIFIED BY '${env.MCP_DATABASE_PASSWORD}';\nALTER USER 'finance_mcp'@'%' IDENTIFIED BY '${env.MCP_DATABASE_PASSWORD}';\nREVOKE ALL PRIVILEGES, GRANT OPTION FROM 'finance_mcp'@'%';\nGRANT SELECT ON \`${env.DB_DATABASE}\`.* TO 'finance_mcp'@'%';\n`;
        run([...compose, 'exec', '-T', 'mysql', 'sh', '-c', 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -u root'], sql);
        run([...compose, 'run', '--rm', '--no-deps', 'agent-migrate']);
        console.log('Chat infrastructure initialized. Finance schema/data are unchanged; see docs/operations/assistant.md.');
        process.exit(0);
    }
    if (action === 'up') {
        for (const name of ['OPENAI_API_KEY', 'AGENT_MODEL', 'MCP_LEGAL_TERMS_HASH', 'MCP_LEGAL_PRIVACY_HASH']) {
            if (!env[name]) throw new Error(`Configure ${name} in .env.chat before starting the assistant.`);
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
