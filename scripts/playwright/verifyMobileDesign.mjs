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
    ['/api/accounts', [account]],
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
    await context.route('**/api/**', async (route) => {
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
    assert.ok(
        await trigger.evaluate((element) => element === document.activeElement),
        'Focus did not return to the opening control',
    );
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
    for (const width of [360, 390, 430]) {
        for (const height of [640, 844]) {
            for (const theme of ['light', 'dark']) {
                const label = `${width}x${height} ${theme}`;
                const authenticated = await prepare(width, height, theme);
                try {
                    await runCase(`${label} subscription`, () =>
                        subscriptionCase(authenticated.page),
                    );
                    await runCase(`${label} sheet`, () => sheetCase(authenticated.page));
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
    const desktop = await prepare(1440, 1000, 'light');
    try {
        await runCase('desktop root header compatibility', async () => {
            await desktop.page.goto(`${appUrl}/admin/subscriptions`);
            const brand = desktop.page
                .locator('header')
                .getByText('Finanzas Simples', { exact: true });
            await brand.waitFor();
            assert.ok((await brand.boundingBox()).width > 100, 'Desktop brand is compressed');
            assert.equal(
                await desktop.page
                    .getByRole('navigation', { name: 'Navegación principal' })
                    .getByRole('link')
                    .count(),
                6,
                'Primary navigation destinations changed',
            );
        });
    } finally {
        await desktop.context.close();
    }
} finally {
    await browser.close();
}

assert.deepEqual(failures, [], failures.join('\n'));
console.log(`${cases} browser regression cases passed without API writes.`);
