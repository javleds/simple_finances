import { readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { parseEnv } from 'node:util';

const financeFile = 'apps/finance/.env';
const mcpFile = 'apps/mcp/.env';
const agentFile = 'apps/agents/.env';

function readSettings(root, file) {
    try {
        return parseEnv(readFileSync(join(root, file), 'utf8'));
    } catch {
        throw new Error(`Cannot read production configuration: ${file}.`);
    }
}

function required(settings, key, file) {
    const value = settings[key];
    if (!value?.trim()) throw new Error(`Missing ${key} in ${file}.`);
    return value;
}

function boolean(settings, key, file) {
    const value = (settings[key] ?? '').toLowerCase();
    if (['true', '(true)', '1'].includes(value)) return true;
    if (['', 'false', '(false)', '0'].includes(value)) return false;
    throw new Error(`Invalid boolean ${key} in ${file}.`);
}

function production(settings, file) {
    if (settings.APP_ENV !== 'production') throw new Error(`APP_ENV must be production in ${file}.`);
    if (boolean(settings, 'APP_DEBUG', file)) throw new Error(`APP_DEBUG must be false in ${file}.`);
}

function matches(left, right, key, leftFile, rightFile) {
    const value = required(left, key, leftFile);
    if (value !== required(right, key, rightFile)) {
        throw new Error(`${key} must match between ${leftFile} and ${rightFile}.`);
    }
    return value;
}

function serviceToken(left, right, key, leftFile, rightFile) {
    const value = matches(left, right, key, leftFile, rightFile);
    if (value.length < 32 || /\s/.test(value)) {
        throw new Error(`${key} must contain at least 32 non-whitespace characters.`);
    }
    return value;
}

function internalUrl(settings, key, expected, file, fallback) {
    if ((settings[key] || fallback) !== expected) {
        throw new Error(`${key} must use the internal Docker service URL in ${file}.`);
    }
}

function postgres(infra, agent) {
    const ownerPassword = required(infra, 'AGENT_DATABASE_OWNER_PASSWORD', '.env');
    const password = required(infra, 'AGENT_DATABASE_PASSWORD', '.env');
    if (!/^[A-Za-z0-9._~-]+$/.test(ownerPassword)) {
        throw new Error('AGENT_DATABASE_OWNER_PASSWORD must be URL-safe for the Compose migrator URL.');
    }
    if (password === ownerPassword) throw new Error('PostgreSQL owner and runtime passwords must differ.');
    const value = required(agent, 'DATABASE_URL', agentFile);
    let valid = false;
    try {
        const url = new URL(value);
        valid = url.protocol === 'postgresql+psycopg:'
            && url.hostname === 'agent-postgres'
            && url.port === '5432'
            && url.pathname === '/agent'
            && url.username === 'agent_runtime'
            && decodeURIComponent(url.password) === password
            && !url.search && !url.hash;
    } catch {
        valid = false;
    }
    if (!valid) throw new Error('DATABASE_URL must use the matching agent_runtime credential and internal PostgreSQL service.');
}

export function validateProduction(root) {
    const finance = readSettings(root, financeFile);
    const project = required(finance, 'COMPOSE_PROJECT_NAME', financeFile);
    if (!/^[a-z0-9][a-z0-9_-]*$/.test(project)) throw new Error('Invalid COMPOSE_PROJECT_NAME in apps/finance/.env.');
    production(finance, financeFile);
    if (!boolean(finance, 'CHAT_ENABLED', financeFile)) return 'finance';

    const infra = readSettings(root, '.env');
    const mcp = readSettings(root, mcpFile);
    const agent = readSettings(root, agentFile);
    if (infra.COMPOSE_PROJECT_NAME && infra.COMPOSE_PROJECT_NAME !== project) {
        throw new Error('COMPOSE_PROJECT_NAME in .env must match the existing Finance project.');
    }
    for (const key of ['DB_DATABASE', 'DB_PASSWORD']) {
        if (infra[key] !== undefined && infra[key] !== finance[key]) {
            throw new Error(`${key} in .env must match ${financeFile} when present.`);
        }
    }
    for (const key of ['AGENT_DATABASE_OWNER_PASSWORD', 'AGENT_DATABASE_PASSWORD']) {
        if (finance[key] !== undefined && finance[key] !== infra[key]) {
            throw new Error(`${key} in ${financeFile} must not override infrastructure credentials.`);
        }
    }
    postgres(infra, agent);
    const financeToken = serviceToken(finance, agent, 'FINANCE_AGENT_SERVICE_TOKEN', financeFile, agentFile);
    const mcpToken = serviceToken(mcp, agent, 'AGENT_MCP_SERVICE_TOKEN', mcpFile, agentFile);
    if (financeToken === mcpToken) throw new Error('Finance-agent and agent-MCP service tokens must differ.');
    internalUrl(finance, 'CHAT_AGENT_URL', 'http://agent-api:8000', financeFile, 'http://agent-api:8000');
    internalUrl(agent, 'MCP_URL', 'http://agent-mcp:8080/mcp', agentFile);
    required(agent, 'OPENROUTER_API_KEY', agentFile);
    required(agent, 'AGENT_MODEL', agentFile);
    production(mcp, mcpFile);
    required(mcp, 'APP_KEY', mcpFile);
    for (const [settings, file] of [[finance, financeFile], [mcp, mcpFile]]) {
        if (settings.DB_CONNECTION !== 'mysql' || settings.DB_HOST !== 'mysql'
            || (settings.DB_PORT || '3306') !== '3306') {
            throw new Error(`MySQL must use the internal mysql service in ${file}.`);
        }
        required(settings, 'DB_DATABASE', file);
        required(settings, 'DB_USERNAME', file);
        required(settings, 'DB_PASSWORD', file);
    }
    matches(finance, mcp, 'DB_DATABASE', financeFile, mcpFile);
    if (mcp.DB_USERNAME.toLowerCase() === 'root' || mcp.DB_USERNAME === finance.DB_USERNAME) {
        throw new Error('MCP requires a separate non-root read-only MySQL account.');
    }
    required(mcp, 'MCP_LEGAL_TERMS_HASH', mcpFile);
    required(mcp, 'MCP_LEGAL_PRIVACY_HASH', mcpFile);
    return 'chat';
}

const script = fileURLToPath(import.meta.url);
if (process.argv[1] && resolve(process.argv[1]) === script) {
    try {
        console.log(validateProduction(resolve(dirname(script), '..')));
    } catch (error) {
        console.error(error instanceof Error ? error.message : 'Invalid production configuration.');
        process.exitCode = 1;
    }
}
