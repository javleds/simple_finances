import assert from 'node:assert/strict';
import { chromium } from 'playwright';

import { installAuthSession } from './auth.mjs';

const appUrl = process.env.PW_APP_URL ?? 'http://127.0.0.1:8000';
const session = {
    token: 'mobile-design-fixture-token',
    tokenType: 'Bearer',
    expiresAt: null,
    user: {
        id: '1',
        name: 'Mobile Fixture',
        email: 'mobile-fixture@example.test',
        isEmailVerified: true,
        phoneNumber: '',
        telegramChatId: null,
    },
};
const account = {
    id: 1,
    user_id: 1,
    name: `Long account ${'investment reserve '.repeat(16)}`,
    description: `Account details ${'long-word-without-a-break'.repeat(16)}`,
    virtual: false,
    credit_card: false,
    balance: '1234567.89',
    feed_account_id: null,
    spent: 0,
    available_credit: null,
    credit_line: null,
    cutoff_day: null,
};
const fixtures = new Map([
    [
        '/api/accounts',
        [
            account,
            { ...account, id: 2, name: 'Second account' },
            { ...account, id: 3, name: 'Third account' },
        ],
    ],
    ['/api/accounts/1', { data: account }],
    ['/api/accounts/1/users', []],
    ['/api/accounts/1/transactions', []],
    ['/api/accounts/1/financial-goals', []],
    [
        '/api/subscriptions',
        [
            {
                id: 1,
                name: 'Fixture subscription',
                amount: '1234.56',
                started_at: '2026-01-01',
                frequency_unit: 1,
                frequency_type: 'months',
                feed_account_id: null,
            },
        ],
    ],
    ['/api/fixed-incomes', [{ id: 1, name: 'Fixture rule', frequency: 'monthly' }]],
    ['/api/fixed-outcomes', []],
]);
const transaction = {
    id: 1,
    account_id: 1,
    user_id: 1,
    concept: 'Desktop movement',
    financial_goal_id: null,
    amount: '123456.78',
    type: 'outcome',
    scheduled_at: '2026-10-10',
    status: 'completed',
    current_user_pending_reimbursement_amount: 120,
    current_user_receivable_reimbursement_amount: 50,
};
fixtures.set('/api/accounts/1/transactions', [transaction]);
for (const [pathname, payload] of [
    [
        '/api/dashboard/graph',
        { data: [{ account_id: 1, account_name: 'Daily account', balance: 2500 }] },
    ],
    [
        '/api/dashboard/accounts',
        { data: { summary: { active_accounts: 3, shared_accounts: 1, virtual_accounts: 1 } } },
    ],
    [
        '/api/dashboard/subscriptions',
        {
            data: {
                annual_total: 1500,
                savings_target_today: 500,
                upcoming_commitment: 1500,
                subscriptions_count: 1,
            },
        },
    ],
    [
        '/api/dashboard/period-summary',
        {
            data: {
                period: { start_date: '2026-10-01', end_date: '2026-10-31' },
                income_total: 5000,
                outcome_total: 2500,
                balance: 2500,
            },
        },
    ],
    [
        '/api/virtual-accounts',
        {
            data: {
                summary: {
                    current_balance: 2500,
                    initial_balance: 2000,
                    manual_contributions: 500,
                    manual_withdrawals: 0,
                    net_capital: 2500,
                    observed_yield: 0,
                    accounts_count: 1,
                },
                accounts: [
                    {
                        account_id: 1,
                        account_name: 'Savings reserve',
                        current_balance: 2500,
                        initial_balance: 2000,
                        manual_contributions: 500,
                        manual_withdrawals: 0,
                        net_capital: 2500,
                        observed_yield: 0,
                        accounts_count: 1,
                    },
                ],
            },
        },
    ],
    ['/api/accounts/1/balance-snapshots', { data: [] }],
    ['/api/account-invites', []],
    ['/api/accounts/1/account-invites', []],
    ['/api/accounts/1/invites', []],
    [
        '/api/accounts/1/ledger',
        [
            {
                id: 1,
                source_type: 'transaction',
                label: 'Opening balance',
                description: 'Initial account funding',
                amount: 2500,
                balance_after: 2500,
            },
        ],
    ],
    ['/api/accounts/1/ledger/diagnostics', { data: { diagnostics: [], repairs: [] } }],
    ['/api/fixed-incomes/1', { id: 1, name: 'Fixture rule', frequency: 'monthly' }],
    [
        '/api/profile',
        {
            id: 1,
            name: 'Desktop Fixture',
            email: 'fixture@example.test',
            is_email_verified: true,
            telegram_chat_id: null,
        },
    ],
    [
        '/api/notification-settings',
        {
            data: {
                notification_types: [{ id: 1, name: 'Weekly summary', checked: true }],
                accounts: [{ id: 1, name: 'Daily account', checked: true }],
            },
        },
    ],
    [
        '/api/transactions',
        {
            data: [transaction],
            meta: { summary: { income_total: 0, outcome_total: 123456.78, balance: -123456.78 } },
        },
    ],
])
    fixtures.set(pathname, payload);
const failures = [];
let cases = 0;
const browser = await chromium.launch();

async function prepare(width, height, theme, authenticated = true) {
    const context = await browser.newContext({
        viewport: { width, height },
        isMobile: width < 640,
        colorScheme: theme,
        reducedMotion: 'reduce',
    });
    const page = await context.newPage();
    page.setDefaultTimeout(10000);
    page.on('pageerror', (error) => {
        failures.push(`Browser error: ${error.message}`);
        console.error(error.message);
    });
    await page.addInitScript((mode) => localStorage.setItem('theme-mode', mode), theme);
    if (authenticated) await installAuthSession(page, session);
    await context.route(`${appUrl}/api/**`, async (route) => {
        const request = route.request();
        const pathname = new URL(request.url()).pathname;
        if (request.method() !== 'GET') {
            failures.push(`Unexpected API write: ${request.method()} ${pathname}`);
            await route.abort();
            return;
        }
        if (!fixtures.has(pathname)) {
            failures.push(`Missing API fixture: ${pathname}`);
            await route.fulfill({ status: 500, json: { message: 'Missing test fixture' } });
            return;
        }
        await route.fulfill({ json: fixtures.get(pathname) });
    });
    return { context, page };
}

async function noOverflow(page) {
    const dimensions = await page.evaluate(() => ({
        width: innerWidth,
        html: document.documentElement.scrollWidth,
        body: document.body.scrollWidth,
    }));
    assert.ok(
        dimensions.html <= dimensions.width + 1 && dimensions.body <= dimensions.width + 1,
        `Horizontal overflow: ${JSON.stringify(dimensions)}`,
    );
}

async function withinViewport(page, locator) {
    const box = await locator.boundingBox();
    const viewport = page.viewportSize();
    assert.ok(
        box &&
            box.x >= -1 &&
            box.y >= -1 &&
            box.x + box.width <= viewport.width + 1 &&
            box.y + box.height <= viewport.height + 1,
        `Element exceeds viewport: ${JSON.stringify(box)}`,
    );
}

async function dialogReady(page, name) {
    const dialog = page.getByRole('dialog', { name, exact: true });
    await dialog.waitFor({ state: 'visible' });
    await page.waitForFunction(
        () => !document.getAnimations().some((animation) => animation.playState === 'running'),
    );
    await withinViewport(page, dialog);
    await withinViewport(page, dialog.locator('.p-dialog-footer'));
    await noOverflow(page);
    return dialog;
}

async function closeWithFocus(page, trigger, dialog) {
    await page.keyboard.press('Escape');
    await dialog.waitFor({ state: 'hidden' });
    await page.waitForFunction(
        (element) => element === document.activeElement,
        await trigger.elementHandle(),
    );
}

async function navigationCase(page) {
    await page.goto(`${appUrl}/admin/accounts`);
    await page.addStyleTag({ content: '.phpdebugbar { display: none !important; }' });
    const navigation = page.getByRole('navigation', { name: 'Navegación principal' });
    const controls = navigation.getByRole('link').or(navigation.getByRole('button'));
    await navigation.waitFor();
    assert.deepEqual(
        (await controls.allInnerTexts()).map((label) => label.trim()),
        ['Inicio', 'Cuentas', 'Ahorro', 'Subs', 'Más'],
    );
    const trigger = navigation.getByRole('button', { name: 'Más', exact: true });
    await trigger.click();
    let dialog = await dialogReady(page, 'Más opciones');
    assert.equal(await trigger.getAttribute('aria-expanded'), 'true');
    assert.deepEqual(
        (await dialog.getByRole('link').allTextContents()).map((label) => label.trim()),
        ['Distribución', 'Pagos', 'Configuración'],
    );
    await closeWithFocus(page, trigger, dialog);
    for (const [label, pathname] of [
        ['Distribución', '/admin/distribution'],
        ['Pagos', '/admin/settings/utilities/credit-card-payoff'],
        ['Configuración', '/admin/settings'],
    ]) {
        await trigger.click();
        dialog = await dialogReady(page, 'Más opciones');
        await dialog.getByRole('link', { name: label, exact: true }).click();
        await page.waitForURL(`${appUrl}${pathname}`);
        await dialog.waitFor({ state: 'hidden' });
        assert.equal(await trigger.getAttribute('aria-expanded'), 'false');
        await noOverflow(page);
    }
    await trigger.click();
    dialog = await dialogReady(page, 'Más opciones');
    const viewport = page.viewportSize();
    await page.setViewportSize({ width: 1280, height: viewport.height });
    await dialog.waitFor({ state: 'hidden' });
    assert.equal(
        await navigation.getByRole('button', { name: 'Más', exact: true }).isVisible(),
        true,
    );
    assert.equal((await navigation.getByRole('link').first().innerText()).trim(), 'Inicio');
    await page.setViewportSize(viewport);
    await trigger.waitFor();
    assert.equal(await trigger.getAttribute('aria-expanded'), 'false');
}

async function desktopNavigationCase(page) {
    const navigation = page.getByRole('navigation', { name: 'Navegación principal' });
    const trigger = navigation.getByRole('button', { name: 'Más', exact: true });
    await trigger.click();
    const submenu = navigation.locator('#desktop-more-navigation');
    await submenu.waitFor();
    assert.deepEqual(
        (await submenu.getByRole('link').allTextContents()).map((label) => label.trim()),
        ['Distribución', 'Pagos', 'Configuración'],
    );
    await trigger.click();
    await submenu.waitFor({ state: 'hidden' });
    await trigger.focus();
    await page.keyboard.press('Enter');
    await submenu.waitFor();
    for (const [label, pathname] of [
        ['Distribución', '/admin/distribution'],
        ['Pagos', '/admin/settings/utilities/credit-card-payoff'],
        ['Configuración', '/admin/settings'],
    ]) {
        if ((await trigger.getAttribute('aria-expanded')) === 'false') await trigger.click();
        await submenu.getByRole('link', { name: label, exact: true }).click();
        await page.waitForURL(`${appUrl}${pathname}`);
        await submenu.waitFor({ state: 'hidden' });
        assert.equal(await trigger.getAttribute('aria-expanded'), 'false');
    }
    await page.goto(`${appUrl}/admin/accounts`);
}

async function savingsLayoutCase(page, width) {
    const virtualDashboard = fixtures.get('/api/virtual-accounts');
    await page.route(`${appUrl}/api/virtual-accounts`, (route) =>
        route.fulfill({
            json: {
                data: {
                    ...virtualDashboard.data,
                    accounts: Array.from({ length: 5 }, (_, index) => ({
                        ...virtualDashboard.data.accounts[0],
                        account_id: index + 1,
                        account_name: `Layout savings ${index + 1}`,
                    })),
                },
            },
        }),
    );
    await page.reload();
    await page.getByText('Layout savings 5', { exact: true }).waitFor();
    await page.getByText('Sin cortes capturados.', { exact: true }).waitFor();
    const summary = await page.getByText('Total actual', { exact: true }).evaluate((element) => {
        const rect = element.closest('.p-card').getBoundingClientRect();
        return { x: rect.x, bottom: rect.bottom };
    });
    const history = await page
        .getByRole('heading', { name: 'Historial observado' })
        .evaluate((element) => {
            const rect = element.closest('.p-card').getBoundingClientRect();
            return { x: rect.x, y: rect.y };
        });
    const accounts = await page
        .getByRole('heading', { name: 'Apartados' })
        .locator('..')
        .boundingBox();
    if (width >= 1024) {
        assert.ok(Math.abs(history.x - summary.x) < 2);
        assert.ok(
            Math.abs(history.y - summary.bottom - 20) < 2,
            'Savings history has an unnecessary gap',
        );
        assert.ok(
            accounts.y + accounts.height > history.y + 50,
            'Savings fixture must have a tall account list',
        );
    } else {
        assert.ok(summary.bottom <= accounts.y);
        assert.ok(accounts.y + accounts.height <= history.y, 'Mobile savings order changed');
    }
    await noOverflow(page);
}

async function dashboardMasonryCase(page, width) {
    const virtualDashboard = fixtures.get('/api/virtual-accounts');
    const virtualAccounts = Array.from({ length: 5 }, (_, index) => ({
        ...virtualDashboard.data.accounts[0],
        account_id: index + 1,
        account_name: `Masonry savings ${index + 1}`,
    }));
    await page.route(`${appUrl}/api/virtual-accounts`, (route) =>
        route.fulfill({
            json: { data: { ...virtualDashboard.data, accounts: virtualAccounts } },
        }),
    );
    await page.route(`${appUrl}/api/dashboard/graph`, (route) =>
        route.fulfill({
            json: {
                data: virtualAccounts.map((account) => ({
                    account_id: account.account_id,
                    account_name: account.account_name,
                    balance: account.current_balance,
                    is_virtual: true,
                })),
            },
        }),
    );
    await page.reload();
    await page.getByRole('button', { name: 'Virtuales', exact: true }).click();
    await page.getByText('Masonry savings 5', { exact: true }).first().waitFor();
    await page.waitForLoadState('networkidle');
    const cardBox = async (heading) =>
        page.getByRole('heading', { name: heading, exact: true }).evaluate((element) => {
            const { x, y, width, height, bottom } = element
                .closest('.p-card')
                .getBoundingClientRect();
            return { x, y, width, height, bottom };
        });
    const balance = await cardBox('Balance por cuenta');
    const planning = await cardBox('Planeación de subscripciones');
    const period = await cardBox('Resumen del periodo');
    const summary = await page.getByText('Cuentas activas', { exact: true }).evaluate((element) => {
        const { y, width, bottom } = element.closest('.p-card').getBoundingClientRect();
        return { y, width, bottom };
    });
    if (width >= 1024) {
        assert.ok(
            summary.bottom <= balance.y && summary.width > balance.width * 1.9,
            'Desktop metrics no longer span both columns',
        );
        assert.ok(
            Math.abs(planning.x - period.x) < 2 && Math.abs(balance.x - period.x) > 100,
            'Short cards are not stacked beside the tall balance card',
        );
        assert.ok(
            Math.abs(period.y - planning.bottom - 24) < 2,
            'Masonry leaves a gap between stacked cards',
        );
        assert.ok(
            period.y < balance.bottom - 50,
            'Period summary still waits for the tall balance card',
        );
    } else {
        assert.ok(
            balance.bottom <= summary.y &&
                summary.bottom <= planning.y &&
                planning.bottom <= period.y,
            'Mobile dashboard order changed',
        );
    }
    await noOverflow(page);
}

async function subscriptionCase(page) {
    await page.goto(`${appUrl}/admin/subscriptions`);
    await page
        .getByText('Fixture subscription', { exact: true })
        .waitFor()
        .catch(async (error) => {
            throw new Error(`${error.message}: ${await page.locator('main').innerText()}`);
        });
    const trigger = page.getByRole('button', { name: 'Crear suscripción', exact: true });
    await trigger.click();
    const dialog = await dialogReady(page, 'Nueva suscripción');
    const box = await dialog.boundingBox();
    assert.ok(
        Math.abs(box.height - page.viewportSize().height) <= 1,
        'Long form does not fill the mobile viewport',
    );
    await dialog.getByLabel('Nombre', { exact: true }).fill('Unsaved fixture');
    const select = dialog.getByRole('combobox', { name: 'Cuenta de alimentación' });
    await select.scrollIntoViewIfNeeded();
    await select.click();
    const overlay = page.locator('.app-select-overlay');
    await overlay.waitFor({ state: 'visible' });
    await withinViewport(page, overlay);
    await noOverflow(page);
    const optionText = await page.getByRole('option').first().textContent();
    assert.ok(
        optionText.includes(account.description),
        `Long option description is missing: ${optionText}`,
    );
    await page.keyboard.press('Escape');
    await overlay.waitFor({ state: 'hidden' });
    await dialog.locator('.p-dialog-content').evaluate((element) => {
        element.scrollTop = element.scrollHeight;
    });
    await withinViewport(page, dialog.locator('.p-dialog-footer'));
    assert.ok(
        await dialog.evaluate((element) => element.contains(document.activeElement)),
        'Focus escaped the dialog',
    );
    await dialog.getByLabel('Nombre', { exact: true }).focus();
    await closeWithFocus(page, trigger, dialog);
    await noOverflow(page);
}

async function sheetCase(page) {
    await page.goto(`${appUrl}/admin/distribution`);
    await page.getByText('Fixture rule', { exact: true }).waitFor();
    const trigger = page.getByRole('button', { name: 'Crear regla', exact: true });
    await trigger.click();
    const dialog = await dialogReady(page, 'Nueva regla');
    const box = await dialog.boundingBox();
    assert.ok(
        Math.abs(box.y + box.height - page.viewportSize().height) <= 1,
        'Short form is not anchored to the bottom',
    );
    await dialog.getByLabel('Nombre', { exact: true }).fill('Unsaved rule');
    await closeWithFocus(page, trigger, dialog);
}

async function legalCase(page) {
    await page.goto(`${appUrl}/auth/register`);
    await page.getByRole('textbox', { name: 'Nombre', exact: true }).fill('Registro conservado');
    await page
        .getByRole('textbox', { name: 'Correo electrónico', exact: true })
        .fill('draft@example.test');
    for (const [linkName, title, path] of [
        ['términos y condiciones', 'Términos y condiciones', '/auth/terms-and-conditions'],
        ['política de privacidad', 'Política de privacidad', '/auth/privacy-policy'],
    ]) {
        const trigger = page.getByRole('link', { name: linkName, exact: true });
        await trigger.click();
        const preview = page.getByRole('region', { name: 'Documento legal', exact: true });
        await preview.getByRole('heading', { name: title, exact: true }).waitFor();
        assert.equal(await page.getByRole('dialog').count(), 0, 'Legal preview opened a modal');
        assert.equal(new URL(page.url()).pathname, '/auth/register', 'Preview left registration');
        assert.equal(await trigger.getAttribute('aria-expanded'), 'true');
        assert.equal(
            await page.getByRole('textbox', { name: 'Nombre', exact: true }).inputValue(),
            'Registro conservado',
        );
        assert.equal(
            await page
                .getByRole('textbox', { name: 'Correo electrónico', exact: true })
                .inputValue(),
            'draft@example.test',
        );
        const pageLink = preview.getByRole('link', {
            name: 'Abrir página completa (nueva pestaña)',
            exact: true,
        });
        assert.equal(await pageLink.getAttribute('href'), path);
        assert.equal(await pageLink.getAttribute('target'), '_blank');
        const documentPage = await page.context().newPage();
        try {
            await documentPage.goto(`${appUrl}${path}`);
            await documentPage
                .getByRole('heading', { name: title, level: 1, exact: true })
                .waitFor();
            await noOverflow(documentPage);
        } finally {
            await documentPage.close();
        }
        await preview.getByRole('button', { name: 'Cerrar documento', exact: true }).click();
        await preview.waitFor({ state: 'hidden' });
        assert.equal(
            await trigger.evaluate((element) => document.activeElement === element),
            true,
            'Focus did not return to legal link',
        );
    }
    assert.equal(
        await page
            .getByRole('checkbox')
            .evaluateAll((elements) => elements.filter((element) => element.checked).length),
        0,
        'Opening legal documents accepted a checkbox',
    );
    await noOverflow(page);
}

async function runCase(label, action) {
    try {
        await action();
        cases += 1;
        console.log(`PASS ${label}`);
    } catch (error) {
        failures.push(`${label}: ${error.message}`);
        console.error(`FAIL ${label}: ${error.message}`);
    }
}

try {
    for (const width of process.env.PW_DESKTOP_ONLY ? [] : [360, 390, 430]) {
        for (const height of [640, 844]) {
            for (const theme of ['light', 'dark']) {
                const label = `${width}x${height} ${theme}`;
                const authenticated = await prepare(width, height, theme);
                try {
                    await runCase(`${label} subscription`, () =>
                        subscriptionCase(authenticated.page),
                    );
                    await runCase(`${label} sheet`, () => sheetCase(authenticated.page));
                    await runCase(`${label} navigation`, () => navigationCase(authenticated.page));
                } finally {
                    await authenticated.context.close();
                }
                const guest = await prepare(width, height, theme, false);
                try {
                    await runCase(`${label} legal`, () => legalCase(guest.page));
                } finally {
                    await guest.context.close();
                }
            }
        }
    }
    for (const width of [390, 1023, 1024, 1280, 1440, 1920]) {
        for (const theme of ['light', 'dark']) {
            const desktop = await prepare(width, 1000, theme);
            try {
                await runCase(`${width}px ${theme} responsive layout`, async () => {
                    const page = desktop.page;
                    await page.goto(`${appUrl}/admin/accounts`);
                    const first = page.getByRole('button').filter({ hasText: account.name });
                    const second = page.getByRole('button').filter({ hasText: 'Second account' });
                    await first.waitFor();
                    await second.waitFor();
                    const navigation = page.getByRole('navigation', {
                        name: 'Navegación principal',
                    });
                    assert.equal(await navigation.getByRole('link').count(), 4);
                    if (width >= 1024) await desktopNavigationCase(page);
                    else await navigationCase(page);
                    await page.goto(`${appUrl}/admin/accounts`);
                    await first.waitFor();
                    assert.equal(
                        (await navigation.getByRole('link').first().innerText()).trim(),
                        'Inicio',
                    );
                    const navBox = await navigation.boundingBox();
                    const mainBox = await page.locator('main').boundingBox();
                    if (width >= 1024) {
                        assert.equal(navBox.x, 0);
                        assert.equal(navBox.y, 0);
                        assert.equal(navBox.width, 240);
                        assert.ok(mainBox.x >= 240 && mainBox.width > 430);
                        const firstBox = await first.boundingBox();
                        const secondBox = await second.boundingBox();
                        assert.ok(
                            Math.abs(firstBox.y - secondBox.y) < 2,
                            'Account cards are not side by side',
                        );
                    } else {
                        assert.ok(mainBox.width <= 430);
                        assert.ok(navBox.y >= 900, 'Mobile navigation moved from the bottom');
                    }
                    if (width >= 1440) {
                        const thirdBox = await page
                            .getByRole('button')
                            .filter({ hasText: 'Third account' })
                            .boundingBox();
                        assert.ok(
                            Math.abs((await first.boundingBox()).y - thirdBox.y) < 2,
                            'Third account is not on the first row',
                        );
                    }
                    await noOverflow(page);
                    await page.goto(`${appUrl}/admin/accounts/1/transactions`);
                    const tabs = page.getByRole('tablist');
                    await tabs.waitFor();
                    if (width >= 1024) {
                        const titleBox = await page
                            .getByRole('heading', { name: account.name, exact: true })
                            .locator('..')
                            .boundingBox();
                        const tabsBox = await tabs.boundingBox();
                        assert.ok(
                            tabsBox.y >= titleBox.y + titleBox.height &&
                                tabsBox.y < titleBox.y + titleBox.height + 100,
                            'Account tabs are not below the account heading',
                        );
                    }
                    await noOverflow(page);
                    for (const path of [
                        '/admin/dashboard',
                        '/admin/virtual-accounts',
                        '/admin/distribution',
                        '/admin/distribution/1',
                        '/admin/settings',
                        '/admin/settings/utilities/credit-card-payoff',
                        '/admin/profile',
                        '/admin/invitations',
                        '/transactions',
                        '/admin/accounts/1/ledger',
                        '/admin/accounts/1/goals',
                        '/admin/accounts/1/users',
                        '/admin/accounts/1/invitations',
                    ]) {
                        await page.goto(`${appUrl}${path}`);
                        await page.locator('main').waitFor();
                        await page.waitForLoadState('networkidle');
                        await page.waitForFunction(
                            () => !document.querySelector('main')?.innerText.includes('Cargando'),
                        );
                        assert.equal(
                            await page.locator('main .p-message-error').count(),
                            0,
                            `Page error at ${path}: ${await page.locator('main').innerText()}`,
                        );
                        assert.ok(
                            !(await page.locator('main').innerText()).includes('invalid_type'),
                            `Invalid fixture at ${path}`,
                        );
                        if (path === '/admin/dashboard') await dashboardMasonryCase(page, width);
                        if (path === '/admin/virtual-accounts')
                            await savingsLayoutCase(page, width);
                        await noOverflow(page);
                        if (width === 1440 && process.env.PW_SCREENSHOT_DIR)
                            await page.screenshot({
                                path: `${process.env.PW_SCREENSHOT_DIR}/${path.replaceAll('/', '-')}-${theme}.png`,
                                fullPage: true,
                            });
                    }
                    await page.goto(`${appUrl}/admin/subscriptions`);
                    await page.getByText('Fixture subscription', { exact: true }).waitFor();
                    const trigger = page.getByRole('button', {
                        name: 'Crear suscripción',
                        exact: true,
                    });
                    await trigger.click();
                    const dialog = await dialogReady(page, 'Nueva suscripción');
                    await dialog
                        .getByLabel('Nombre', { exact: true })
                        .fill('Preserved resize draft');
                    await page.setViewportSize({ width: 390, height: 844 });
                    assert.equal(
                        await dialog.getByLabel('Nombre', { exact: true }).inputValue(),
                        'Preserved resize draft',
                    );
                    await withinViewport(page, dialog);
                    await page.setViewportSize({ width, height: 1000 });
                    assert.equal(
                        await dialog.getByLabel('Nombre', { exact: true }).inputValue(),
                        'Preserved resize draft',
                    );
                    await withinViewport(page, dialog);
                    await closeWithFocus(page, trigger, dialog);
                    await noOverflow(page);
                    if (width === 1440 && process.env.PW_SCREENSHOT_DIR) {
                        await page.screenshot({
                            path: `${process.env.PW_SCREENSHOT_DIR}/subscriptions-${theme}.png`,
                            fullPage: true,
                        });
                        await page.goto(`${appUrl}/admin/accounts`);
                        await first.waitFor();
                        await page.screenshot({
                            path: `${process.env.PW_SCREENSHOT_DIR}/accounts-${theme}.png`,
                            fullPage: true,
                        });
                    }
                });
            } finally {
                await desktop.context.close();
            }
        }
    }
} finally {
    await browser.close();
}

assert.deepEqual(failures, [], failures.join('\n'));
console.log(`${cases} browser regression cases passed without API writes.`);
