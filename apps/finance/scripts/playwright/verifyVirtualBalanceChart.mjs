import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { installAuthSession } from './auth.mjs';

const appUrl = process.env.PW_APP_URL ?? 'http://127.0.0.1:8000';
const browser = await chromium.launch();
const errors = [];
let cases = 0;
const virtualAccounts = [
    {
        account_id: 1,
        account_name: 'Ahorro con ganancias',
        current_balance: 10500,
        net_capital: 10000,
        observed_yield: 500,
    },
    {
        account_id: 2,
        account_name: 'Ahorro con pérdidas',
        current_balance: 9800,
        net_capital: 10000,
        observed_yield: -200,
    },
    {
        account_id: 3,
        account_name: 'Saldo histórico',
        current_balance: 11000,
        net_capital: 10000,
        observed_yield: 500,
    },
].map((account) => ({
    ...account,
    initial_balance: 0,
    manual_contributions: 10000,
    manual_withdrawals: 0,
    color: '#0e6474',
    latest_snapshot: null,
}));
const fixtures = new Map([
    [
        '/api/dashboard/graph',
        {
            data: [
                ...virtualAccounts.map((account) => ({
                    ...account,
                    balance: account.current_balance,
                    is_virtual: true,
                })),
                { account_id: 4, account_name: 'Cuenta física', balance: 2000, is_virtual: false },
            ],
        },
    ],
    [
        '/api/dashboard/accounts',
        { data: { summary: { active_accounts: 4, shared_accounts: 0, virtual_accounts: 3 } } },
    ],
    [
        '/api/dashboard/subscriptions',
        {
            data: {
                annual_total: 0,
                savings_target_today: 0,
                upcoming_commitment: 0,
                subscriptions_count: 0,
                nearest_payment: null,
            },
        },
    ],
    [
        '/api/dashboard/period-summary',
        {
            data: {
                period: { start_date: '2026-01-01', end_date: '2026-01-31' },
                income_total: 0,
                outcome_total: 0,
                balance: 0,
            },
        },
    ],
    ['/api/accounts', []],
    [
        '/api/virtual-accounts',
        {
            data: {
                summary: {
                    current_balance: 31300,
                    initial_balance: 0,
                    manual_contributions: 30000,
                    manual_withdrawals: 0,
                    net_capital: 30000,
                    observed_yield: 800,
                    accounts_count: 3,
                },
                accounts: virtualAccounts,
            },
        },
    ],
]);

try {
    for (const width of [360, 390, 430]) {
        for (const theme of ['light', 'dark']) {
            const context = await browser.newContext({
                viewport: { width, height: 844 },
                isMobile: true,
                reducedMotion: 'reduce',
                colorScheme: theme,
            });
            const page = await context.newPage();
            page.setDefaultTimeout(10000);
            page.on('pageerror', (error) => errors.push(error.message));
            await page.addInitScript((mode) => localStorage.setItem('theme-mode', mode), theme);
            await installAuthSession(page, {
                token: 'chart-fixture',
                tokenType: 'Bearer',
                expiresAt: null,
                user: {
                    id: '1',
                    name: 'Chart Fixture',
                    email: 'chart@example.test',
                    isEmailVerified: true,
                    phoneNumber: '',
                    telegramChatId: null,
                },
            });
            let rejectVirtual = false;
            await context.route('**/api/**', async (route) => {
                const request = route.request();
                const path = new URL(request.url()).pathname;
                if (request.method() !== 'GET' || !fixtures.has(path)) {
                    errors.push(`Unexpected API request: ${request.method()} ${path}`);
                    await route.abort();
                    return;
                }
                if (path === '/api/virtual-accounts' && rejectVirtual) {
                    await route.fulfill({ status: 503, json: { message: 'Fixture unavailable' } });
                    return;
                }
                await route.fulfill({ json: fixtures.get(path) });
            });
            try {
                await page.goto(`${appUrl}/admin/dashboard`);
                await page.getByRole('button', { name: 'Virtuales', exact: true }).click();
                await page.getByLabel('Segmentos de la barra').waitFor();
                const list = page.getByLabel('Balances por cuenta');
                assert.ok((await list.innerText()).includes('$10,500.00'));
                assert.ok((await list.innerText()).includes('-$200.00'));
                assert.ok((await list.innerText()).includes('Saldo sin desglose: $500.00'));
                await page.waitForFunction(() => {
                    const list = document.querySelector('[aria-label="Balances por cuenta"]');
                    const svg = list?.parentElement?.querySelector('svg');
                    const bars = [...(svg?.querySelectorAll('rect') ?? [])].filter(
                        (element) =>
                            Number(element.getAttribute('height')) === 16 &&
                            Number(element.getAttribute('width')) > 0,
                    );
                    return bars.length >= 7;
                });
                const geometry = await list.evaluate((element) => {
                    const svg = element.parentElement.querySelector('svg');
                    return [...svg.querySelectorAll('rect')]
                        .map((rect) => ({
                            x: Number(rect.getAttribute('x')),
                            y: Number(rect.getAttribute('y')),
                            width: Number(rect.getAttribute('width')),
                            height: Number(rect.getAttribute('height')),
                        }))
                        .filter((rect) => rect.height === 16 && rect.width > 0);
                });
                const firstY = Math.min(...geometry.map((bar) => bar.y));
                const first = geometry
                    .filter((bar) => Math.abs(bar.y - firstY) < 1)
                    .sort((a, b) => a.x - b.x);
                assert.equal(first.length, 2, 'Savings and gains are not two segments of one bar');
                assert.ok(
                    Math.abs(first[0].x + first[0].width - first[1].x) < 1,
                    'Segments are not adjacent',
                );
                assert.ok(
                    Math.abs(first[0].width / first[1].width - 20) < 0.1,
                    'Segments do not match 10000 savings and 500 gains',
                );
                assert.ok(
                    await page.evaluate(
                        () => document.documentElement.scrollWidth <= innerWidth + 1,
                    ),
                    'Chart overflows viewport',
                );
                await page.getByRole('button', { name: 'Físicas', exact: true }).click();
                await list.getByText('Cuenta física', { exact: true }).waitFor();
                assert.equal(await page.getByLabel('Segmentos de la barra').count(), 0);
                rejectVirtual = true;
                await page.getByRole('button', { name: 'Virtuales', exact: true }).click();
                await page
                    .getByText('No fue posible cargar el desglose de cuentas virtuales.')
                    .waitFor();
                rejectVirtual = false;
                await page.getByRole('button', { name: 'Reintentar', exact: true }).click();
                await page.getByLabel('Segmentos de la barra').waitFor();
                cases += 1;
                console.log(
                    `PASS ${width} ${theme}: stacked geometry, totals, physical switch and retry`,
                );
            } finally {
                await context.close();
            }
        }
    }
} finally {
    await browser.close();
}
assert.deepEqual(errors, []);
console.log(`${cases} chart browser cases passed without API writes.`);
