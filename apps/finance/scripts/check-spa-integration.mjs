import assert from 'node:assert/strict';
import { stat, utimes } from 'node:fs/promises';
import { chromium } from 'playwright';

const origin = process.env.PW_APP_URL ?? 'http://127.0.0.1:8000';
const browser = await chromium.launch({ headless: true });
try {
    const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
    page.setDefaultTimeout(10000);
    const errors = [];
    const sockets = [];
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('websocket', (socket) => sockets.push(socket));
    const response = await page.goto(`${origin}/auth`);
    assert.equal(response.status(), 200);
    await page.getByRole('button', { name: 'Iniciar sesión', exact: true }).waitFor();
    assert.equal(await page.title(), 'fin-si');
    assert.equal(await page.locator('html').getAttribute('lang'), 'es');
    const buttonStyle = await page
        .getByRole('button', { name: 'Iniciar sesión', exact: true })
        .evaluate((button) => getComputedStyle(button).backgroundColor);
    assert.notEqual(buttonStyle, 'rgba(0, 0, 0, 0)');

    if (process.argv.includes('--hmr')) {
        const socket = sockets.find((candidate) => candidate.url().includes('5173'));
        assert.ok(socket, 'Vite websocket must be connected');
        const stylesheet = new URL('../resources/js/main.css', import.meta.url);
        const originalStat = await stat(stylesheet);
        try {
            const update = socket.waitForEvent('framereceived', {
                predicate: (frame) => String(frame.payload).includes('"type":"update"'),
                timeout: 10000,
            });
            await utimes(stylesheet, originalStat.atime, new Date());
            await update;
        } finally {
            await utimes(stylesheet, originalStat.atime, originalStat.mtime);
        }
    } else {
        assert.equal(await page.locator('script[src*="5173"]').count(), 0);
        assert.ok(await page.locator('script[src*="/build/assets/"]').count());
    }

    await page.goto(`${origin}/admin/accounts`);
    await page.waitForURL(`${origin}/auth`);
    await page.getByRole('button', { name: 'Iniciar sesión', exact: true }).waitFor();
    await page.goto(`${origin}/unrecognized-client-route`);
    await page
        .locator('#app')
        .filter({ hasText: /404|encontrad/i })
        .waitFor();
    const missing = await page.request.get(`${origin}/api/missing`, {
        headers: { Accept: 'application/json' },
    });
    assert.equal(missing.status(), 404);
    assert.match(missing.headers()['content-type'], /application\/json/);
    assert.equal(errors.length, 0, errors.join('\n'));
    console.log(
        `SPA integration passed (${process.argv.includes('--hmr') ? 'HMR' : 'production assets'})`,
    );
} finally {
    await browser.close();
}
