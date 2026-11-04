import { randomBytes } from 'node:crypto';
import { existsSync, readFileSync, writeFileSync, chmodSync } from 'node:fs';
import { join } from 'node:path';
import { parseEnv } from 'node:util';

export function readSettings(root, file) {
    const path = join(root, file);
    if (!existsSync(path)) throw new Error(`Missing ${file}. Run make chat-config first.`);
    return parseEnv(readFileSync(path, 'utf8'));
}

export function configure(root) {
    if (existsSync(join(root, '.env.chat'))) {
        throw new Error('Migrate .env.chat using docs/operations/assistant.md before running chat-config. Existing credentials must be preserved.');
    }
    const files = ['.env', 'apps/finance/.env', 'apps/mcp/.env', 'apps/agents/.env'];
    const documents = files.map(file => {
        const path = join(root, file);
        const exists = existsSync(path);
        const content = readFileSync(exists ? path : `${path}.example`, 'utf8');
        return { file, path, exists, content, values: parseEnv(content) };
    });
    const [infra, finance, mcp, agent] = documents;
    const secret = () => randomBytes(32).toString('hex');
    function fill(document, key, value) {
        if (document.values[key]) return;
        const line = `${key}='${value}'`;
        if (value.includes("'")) throw new Error(`Configure ${key} manually in ${document.file}.`);
        const pattern = new RegExp(`^${key}=.*$`, 'm');
        document.content = pattern.test(document.content)
            ? document.content.replace(pattern, () => line)
            : `${document.content.trimEnd()}\n${line}\n`;
        document.values[key] = value;
    }
    function pair(left, right, key) {
        if (left.values[key] && right.values[key] && left.values[key] !== right.values[key]) {
            throw new Error(`${key} differs between ${left.file} and ${right.file}. Reconcile it explicitly.`);
        }
        const value = left.values[key] || right.values[key] || secret();
        fill(left, key, value);
        fill(right, key, value);
    }
    for (const [key, value] of Object.entries({ COMPOSE_PROJECT_NAME: 'fin-si-chat', CHAT_WEB_PORT: '8080', DB_DATABASE: 'finance' })) {
        fill(infra, key, value);
    }
    for (const key of ['DB_PASSWORD', 'AGENT_DATABASE_OWNER_PASSWORD', 'AGENT_DATABASE_PASSWORD']) {
        fill(infra, key, secret());
    }
    pair(finance, agent, 'FINANCE_AGENT_SERVICE_TOKEN');
    pair(mcp, agent, 'AGENT_MCP_SERVICE_TOKEN');
    fill(mcp, 'APP_KEY', `base64:${randomBytes(32).toString('base64')}`);
    fill(mcp, 'DB_PASSWORD', secret());
    fill(agent, 'DATABASE_URL', `postgresql+psycopg://agent_runtime:${encodeURIComponent(infra.values.AGENT_DATABASE_PASSWORD)}@agent-postgres:5432/agent`);
    if (!finance.exists) {
        for (const [key, value] of Object.entries({
            DB_CONNECTION: 'mysql', DB_HOST: 'mysql', DB_PORT: '3306',
            DB_DATABASE: infra.values.DB_DATABASE, DB_USERNAME: 'root', DB_PASSWORD: infra.values.DB_PASSWORD,
            CHAT_ENABLED: 'true',
        })) {
            finance.values[key] = '';
            fill(finance, key, value);
        }
    }
    if (!mcp.exists) {
        mcp.values.DB_DATABASE = '';
        fill(mcp, 'DB_DATABASE', infra.values.DB_DATABASE);
    }
    for (const document of documents) {
        writeFileSync(document.path, document.content, { mode: 0o600 });
        chmodSync(document.path, 0o600);
    }
}
