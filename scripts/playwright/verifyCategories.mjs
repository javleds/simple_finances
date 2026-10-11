import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { chromium } from 'playwright';
import { installAuthSession } from './auth.mjs';

const appUrl = process.env.PW_APP_URL ?? 'http://127.0.0.1:8000';
const manifest = JSON.parse(
    readFileSync(new URL('../../public/build/manifest.json', import.meta.url), 'utf8'),
);
const entry = manifest['resources/js/main.ts'];
const css = [...new Set(Object.values(manifest).flatMap((asset) => asset.css ?? []))];
const shell = `<html lang="es"><head><meta name="viewport" content="width=device-width, initial-scale=1">${css.map((file) => `<link rel="stylesheet" href="/build/${file}">`).join('')}<script type="module" src="/build/${entry.file}"></script></head><body><div id="app"></div></body></html>`;
const browser = await chromium.launch();
const session = {
    token: 'category-fixture-token',
    tokenType: 'Bearer',
    expiresAt: null,
    user: {
        id: '1',
        name: 'Ana',
        email: 'ana@example.test',
        isEmailVerified: true,
        phoneNumber: '',
        telegramChatId: null,
    },
};

try {
    for (const width of [390, 1280]) {
        const context = await browser.newContext({
            viewport: { width, height: 900 },
            reducedMotion: 'reduce',
        });
        const page = await context.newPage();
        page.setDefaultTimeout(10000);
        await installAuthSession(page, session);
        await context.route(`${appUrl}/admin/**`, (route) =>
            route.request().resourceType() === 'document'
                ? route.fulfill({ contentType: 'text/html', body: shell })
                : route.fallback(),
        );
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        page.on('requestfailed', (request) =>
            console.error('Failed request:', request.url(), request.failure()?.errorText),
        );
        const account = {
            id: 1,
            user_id: 1,
            name: 'Casa',
            balance: 500,
            uses_shared_categories: true,
            virtual: false,
            credit_card: false,
            spent: 0,
            available_credit: null,
            credit_line: null,
            cutoff_day: null,
            feed_account_id: null,
        };
        const members = [
            { id: 1, name: 'Ana', email: 'ana@example.test', pivot: { percentage: 50 } },
            { id: 2, name: 'Luis', email: 'luis@example.test', pivot: { percentage: 50 } },
        ];
        account.users = members;
        const categories = [
            {
                id: 1,
                name: 'Comida',
                account_id: 1,
                scope: 'shared',
                transactions_count: 2,
                can_manage: true,
            },
        ];
        let createCount = 0;
        let deletedPayload = null;
        let rejectCreation = false;
        await context.route(`${appUrl}/api/**`, async (route) => {
            const request = route.request();
            const path = new URL(request.url()).pathname;
            if (path === '/api/accounts/1/categories' && request.method() === 'POST') {
                createCount += 1;
                if (rejectCreation)
                    return route.fulfill({
                        status: 422,
                        json: { message: 'No fue posible crear la categoría.' },
                    });
                const category = {
                    ...categories[0],
                    id: categories.length + 1,
                    name: request.postDataJSON().name,
                    transactions_count: 0,
                };
                categories.push(category);
                return route.fulfill({ status: 201, json: { data: category } });
            }
            if (path.startsWith('/api/accounts/1/categories/') && request.method() === 'DELETE') {
                deletedPayload = request.postDataJSON();
                categories.splice(
                    categories.findIndex(
                        (category) => String(category.id) === path.split('/').at(-1),
                    ),
                    1,
                );
                return route.fulfill({ json: { data: null } });
            }
            if (path === '/api/accounts/1/categories')
                return route.fulfill({
                    json: {
                        data: categories,
                        meta: {
                            scope: 'shared',
                            account_id: 1,
                            account_name: 'Casa',
                            can_manage: true,
                        },
                    },
                });
            if (path === '/api/categories')
                return route.fulfill({
                    json: {
                        data: [],
                        meta: { scope: 'personal', account_id: null, can_manage: true },
                    },
                });
            if (path === '/api/accounts') return route.fulfill({ json: { data: [account] } });
            if (path === '/api/accounts/1') return route.fulfill({ json: { data: account } });
            if (path === '/api/accounts/1/users') return route.fulfill({ json: { data: members } });
            return route.fulfill({ json: { data: [] } });
        });
        await page.goto(`${appUrl}/admin/accounts/1/transactions`);
        await page
            .getByRole('button', { name: 'Crear transacción', exact: true })
            .click()
            .catch(async (error) => {
                console.error(
                    'Page diagnostic:',
                    page.url(),
                    (await page.locator('body').innerText()).slice(0, 1000),
                    errors,
                );
                throw error;
            });
        const dialog = page.getByRole('dialog');
        await dialog.getByLabel('Concepto', { exact: true }).fill('Compra conservada');
        const categorySelect = dialog.getByRole('combobox', { name: 'Categoría', exact: true });
        await categorySelect.click();
        await page.getByPlaceholder('Buscar o crear categoría').fill('Transporte');
        await page.getByRole('option', { name: 'Crear «Transporte»', exact: true }).click();
        await page.waitForFunction(() =>
            document.getElementById('transaction-category')?.textContent?.includes('Transporte'),
        );
        assert.equal(createCount, 1);
        if (process.env.PW_CATEGORY_SCREENSHOTS)
            await page.screenshot({
                path: `${process.env.PW_CATEGORY_SCREENSHOTS}/categories-${width}.png`,
            });
        assert.equal(
            await dialog.getByLabel('Concepto', { exact: true }).inputValue(),
            'Compra conservada',
        );
        assert.ok((await dialog.innerText()).includes('Compartida · Casa'));
        const conceptBox = await dialog.getByLabel('Concepto', { exact: true }).boundingBox();
        const categoryBox = await categorySelect.evaluate((element) => {
            const rect = element.closest('.p-select').getBoundingClientRect();
            return { y: rect.y, width: rect.width };
        });
        assert.ok(categoryBox.y > conceptBox.y, 'Category must follow concept');
        assert.ok(categoryBox.width > conceptBox.width - 5, 'Category must occupy full form width');
        await categorySelect.click();
        await page.getByPlaceholder('Buscar o crear categoría').fill('transporte');
        assert.equal(
            await page.getByRole('option', { name: 'Crear «transporte»', exact: true }).count(),
            0,
        );
        await page.keyboard.press('Escape');
        rejectCreation = true;
        await categorySelect.click();
        await page.getByPlaceholder('Buscar o crear categoría').fill('Rechazada');
        await page.getByRole('option', { name: 'Crear «Rechazada»', exact: true }).click();
        await dialog.getByText('No fue posible crear la categoría.', { exact: true }).waitFor();
        assert.ok(
            (await categorySelect.innerText()).includes('Transporte'),
            'Creation failure must preserve selection',
        );
        assert.equal(
            await dialog.getByLabel('Concepto', { exact: true }).inputValue(),
            'Compra conservada',
        );
        await dialog.getByRole('button', { name: 'Cancelar', exact: true }).click();
        await page.goto(`${appUrl}/admin/categories`);
        await page.getByRole('combobox', { name: 'Catálogo', exact: true }).click();
        await page.getByRole('option', { name: 'Casa', exact: true }).click();
        const food = page.getByRole('listitem').filter({ hasText: 'Comida' });
        await food.getByRole('button', { name: 'Abrir acciones' }).click();
        await page.getByRole('menuitem', { name: 'Eliminar', exact: true }).click();
        const deletion = page.getByRole('dialog', { name: 'Eliminar categoría', exact: true });
        await deletion.waitFor();
        assert.ok((await deletion.innerText()).includes('otros miembros'));
        assert.equal(
            await deletion
                .getByRole('button', { name: 'Eliminar categoría', exact: true })
                .isDisabled(),
            true,
        );
        await deletion.getByRole('combobox', { name: 'Qué hacer con los movimientos' }).click();
        await page.getByRole('option', { name: 'Moverlos a otra categoría', exact: true }).click();
        await deletion.getByRole('combobox', { name: 'Categoría de destino' }).click();
        await page.getByRole('option', { name: 'Transporte', exact: true }).click();
        await deletion.getByRole('button', { name: 'Eliminar categoría', exact: true }).click();
        await deletion.waitFor({ state: 'hidden' });
        assert.deepEqual(deletedPayload, { action: 'reassign', target_category_id: '2' });
        const dimensions = await page.evaluate(() => ({
            width: innerWidth,
            body: document.body.scrollWidth,
            html: document.documentElement.scrollWidth,
        }));
        assert.ok(
            dimensions.body <= dimensions.width + 1 && dimensions.html <= dimensions.width + 1,
            'Page must not overflow horizontally',
        );
        assert.deepEqual(errors, []);
        console.log(
            `Category creation, failed creation, shared deletion and layout passed at ${width}px`,
        );
        await context.close();
    }
} finally {
    await browser.close();
}
