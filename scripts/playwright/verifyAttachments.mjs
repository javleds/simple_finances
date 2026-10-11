import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { chromium } from 'playwright';
import { installAuthSession } from './auth.mjs';

const appUrl = process.env.PW_APP_URL ?? 'http://127.0.0.1:8000';
const manifest = JSON.parse(readFileSync(new URL('../../public/build/manifest.json', import.meta.url), 'utf8'));
const entry = manifest['resources/js/main.ts'];
const css = [...new Set(Object.values(manifest).flatMap((asset) => asset.css ?? []))];
const shell = `<html lang="es"><head><meta name="viewport" content="width=device-width, initial-scale=1">${css.map((file) => `<link rel="stylesheet" href="/build/${file}">`).join('')}<script type="module" src="/build/${entry.file}"></script></head><body><div id="app"></div></body></html>`;
const session = {
    token: 'attachments-fixture-token', tokenType: 'Bearer', expiresAt: null,
    user: { id: '1', name: 'Ana', email: 'ana@example.test', isEmailVerified: true, phoneNumber: '', telegramChatId: null },
};
const fileConfig = {
    max_files: 5,
    max_file_bytes: { image: 5242880, pdf: 5242880, xml: 1048576, zip: 5242880 },
    max_transaction_bytes: 15728640,
    accepted_extensions: ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif', 'pdf', 'xml', 'zip'],
    poll_interval_ms: 5000,
};
const documentFile = { name: 'factura.xml', mimeType: 'application/xml', buffer: Buffer.from('<?xml version="1.0"?><invoice total="100"/>') };

const browser = await chromium.launch();
try {
    for (const width of [390, 1280]) {
        for (const shared of [false, true]) {
            const context = await browser.newContext({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
            const page = await context.newPage();
            const imageBase64 = await page.evaluate(() => {
                const canvas = document.createElement('canvas');
                canvas.width = 600;
                canvas.height = 900;
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, 600, 900);
                ctx.fillStyle = '#173840';
                ctx.font = 'bold 36px sans-serif';
                ctx.fillText('COMPROBANTE', 70, 100);
                ctx.font = '26px sans-serif';
                ctx.fillText('Compra de prueba', 70, 180);
                ctx.fillText('Total: $100.00', 70, 250);
                ctx.fillRect(70, 300, 460, 2);
                ctx.fillText('Gracias por tu compra', 70, 370);
                return canvas.toDataURL('image/png').split(',')[1];
            });
            const imageFile = { name: 'ticket.png', mimeType: 'image/png', buffer: Buffer.from(imageBase64, 'base64') };

            page.setDefaultTimeout(10000);
            await installAuthSession(page, session);
            await context.route(`${appUrl}/admin/**`, (route) => route.request().resourceType() === 'document' ? route.fulfill({ contentType: 'text/html', body: shell }) : route.fallback());
            const errors = [];
            page.on('pageerror', (error) => errors.push(error.message));
            const members = [{ id: 1, name: 'Ana', email: 'ana@example.test', pivot: { percentage: shared ? 50 : 100 } }];
            if (shared) members.push({ id: 2, name: 'Luis', email: 'luis@example.test', pivot: { percentage: 50 } });
            const account = { id: 1, user_id: 1, name: shared ? 'Casa compartida' : 'Personal', balance: 500, uses_shared_categories: shared, users: members, virtual: false, credit_card: false, spent: 0, available_credit: null, credit_line: null, cutoff_day: null, next_cutoff_date: null, feed_account_id: null };
            const transactions = [];
            const attachments = [];
            let mutationCount = 0;
            let uploadCount = 0;
            let failUpload = true;
            let attachmentListCount = 0;
            let downloadCount = 0;
            let imageDownloadCount = 0;
            const unknown = [];
            await context.route(`${appUrl}/api/**`, async (route) => {
                const request = route.request();
                const path = new URL(request.url()).pathname;
                assert.equal(request.headers().authorization, 'Bearer attachments-fixture-token');
                if (path === '/api/transaction-attachments/config') return route.fulfill({ json: { data: fileConfig } });
                if (path === '/api/accounts/1/transactions' && request.method() === 'POST') {
                    mutationCount += 1;
                    const payload = request.postDataJSON();
                    const transaction = { id: 11, account_id: 1, user_id: 1, user: members[0], account, paid_by_user_id: 1, paid_by_user: members[0], attachments_count: attachments.length, ...payload };
                    transactions.unshift(transaction);
                    return route.fulfill({ status: 201, json: { data: transaction, meta: { account_balance: 400 } } });
                }
                if (path === '/api/transactions/11/attachments' && request.method() === 'POST') {
                    uploadCount += 1;
                    assert.match(request.headers()['content-type'], /^multipart\/form-data; boundary=/);
                    assert.ok(request.postDataBuffer().includes(Buffer.from('upload_id')));
                    if (failUpload) {
                        failUpload = false;
                        return route.fulfill({ status: 503, json: { message: 'No fue posible subir el comprobante.' } });
                    }
                    const attachment = { id: 21, original_name: 'factura.xml', mime_type: null, size_bytes: documentFile.buffer.length, status: 'pending', error_message: null, can_manage: true, can_retry: false, created_at: '2026-10-10T12:00:00Z' };
                    attachments.push(attachment);
                    transactions[0].attachments_count = attachments.length;
                    return route.fulfill({ status: 202, json: { data: attachment } });
                }
                if (path === '/api/transactions/11/attachments') {
                    attachmentListCount += 1;
                    return route.fulfill({ json: { data: attachments, meta: { can_manage: true, count: attachments.length, total_bytes: attachments.reduce((sum, item) => sum + item.size_bytes, 0) } } });
                }
                if (/^\/api\/transactions\/11\/attachments\/(22|23)\/download$/.test(path)) {
                    imageDownloadCount += 1;
                    return route.fulfill({ contentType: 'image/png', body: imageFile.buffer });
                }
                if (path === '/api/transactions/11/attachments/21/download') {
                    downloadCount += 1;
                    return route.fulfill({ contentType: 'application/xml', headers: { 'Content-Disposition': 'attachment; filename="factura.xml"' }, body: documentFile.buffer });
                }
                if (path === '/api/accounts/1/transactions') return route.fulfill({ json: { data: transactions, meta: { current_page: 1, last_page: 1, per_page: 20, total: transactions.length } } });
                if (path === '/api/accounts') return route.fulfill({ json: { data: [account] } });
                if (path === '/api/accounts/1') return route.fulfill({ json: { data: account } });
                if (path === '/api/accounts/1/users') return route.fulfill({ json: { data: members } });
                if (path === '/api/accounts/1/categories') return route.fulfill({ json: { data: [], meta: { scope: shared ? 'shared' : 'personal', account_id: shared ? 1 : null, account_name: account.name, can_manage: true } } });
                if (request.method() !== 'GET') unknown.push(`${request.method()} ${path}`);
                return route.fulfill({ json: { data: [] } });
            });
            await page.goto(`${appUrl}/admin/accounts/1/transactions`);
            await page.getByRole('button', { name: 'Crear transacción', exact: true }).click();
            const dialog = page.getByRole('dialog').first();
            await dialog.getByLabel('Concepto', { exact: true }).fill('Compra con comprobante');
            await dialog.getByLabel('Cantidad', { exact: true }).fill('100');
            await dialog.getByRole('button', { name: /Comprobantes/ }).click();
            await dialog.locator('input[type="file"]:not([capture])').setInputFiles(documentFile);
            await dialog.getByText(/factura\.xml/).waitFor();
            await dialog.locator('input[type="file"]:not([capture])').setInputFiles(imageFile);
            await dialog.getByRole('button', { name: 'Ver imagen ticket.png', exact: true }).click();
            const localViewer = page.getByRole('dialog').last();
            await localViewer.getByRole('status', { name: 'Nivel de zoom' }).waitFor();
            await localViewer.getByRole('button', { name: 'Cerrar visor', exact: true }).click();
            await dialog.locator('li').filter({ hasText: 'ticket.png' }).getByRole('button', { name: 'Quitar', exact: true }).click();

            await dialog.getByRole('button', { name: 'Crear transacción', exact: true }).click();
            await dialog.getByText(/El movimiento se guardó, pero faltan comprobantes/).waitFor();
            assert.equal(mutationCount, 1, 'Upload failure must preserve exactly one financial mutation');
            await dialog.getByRole('button', { name: /Reintentar/ }).click();
            await page.waitForFunction(() => !document.querySelector('[role="dialog"]'));
            assert.equal(mutationCount, 1, 'Upload retry must not recreate the transaction');
            assert.equal(uploadCount, 2);
            await page.getByRole('button', { name: /comprobante/i }).click();
            const receipts = page.getByRole('dialog').last();
            await receipts.getByText(/factura\.xml/).waitFor();
            assert.ok((await receipts.innerText()).includes('espera') || (await receipts.innerText()).includes('preparando'));
            attachments[0].status = 'ready';
            for (const id of [22, 23]) attachments.push({ ...attachments[0], id, original_name: `ticket-${id}.webp`, mime_type: 'image/webp' });

            await receipts.getByRole('button', { name: 'Descargar', exact: true }).first().waitFor({ timeout: 12000 });
            await receipts.getByRole('button', { name: 'Descargar', exact: true }).first().click();
            await page.waitForTimeout(200);
            assert.equal(downloadCount, 1);
            await receipts.getByRole('button', { name: 'Ver imagen ticket-22.webp', exact: true }).click();
            const viewer = page.getByRole('dialog').last();
            await viewer.getByText('1 / 2', { exact: true }).waitFor();
            if (width >= 1280) assert.ok((await viewer.boundingBox()).width >= 900, 'Desktop viewer must use a wide modal');
            await viewer.getByRole('button', { name: 'Ampliar imagen', exact: true }).click();
            assert.equal(await viewer.getByRole('status', { name: 'Nivel de zoom' }).innerText(), '150%');
            await viewer.getByRole('button', { name: 'Imagen siguiente', exact: true }).click();
            await viewer.getByText('2 / 2', { exact: true }).waitFor();
            assert.equal(await viewer.getByRole('status', { name: 'Nivel de zoom' }).innerText(), '100%');
            await viewer.getByRole('button', { name: 'Descargar imagen', exact: true }).click();
            assert.equal(imageDownloadCount, 2, 'Gallery, viewer and downloads must reuse the image blobs');
            if (process.env.PW_ATTACHMENT_SCREENSHOTS) await page.screenshot({ path: `${process.env.PW_ATTACHMENT_SCREENSHOTS}/viewer-${shared ? 'shared' : 'personal'}-${width}.png` });
            await page.keyboard.press('Escape');
            await page.getByRole('toolbar', { name: 'Controles de imagen' }).waitFor({ state: 'hidden' });
            await receipts.getByRole('button', { name: 'Ver imagen ticket-22.webp', exact: true }).waitFor();
            await page.waitForTimeout(350);
            assert.equal(await page.getByRole('dialog').count(), 1, 'Escape must preserve the receipts dialog');
            await receipts.getByRole('button', { name: 'Ver imagen ticket-22.webp', exact: true }).waitFor();

            assert.ok(attachmentListCount >= 2, 'Pending attachments must refresh without manual reload');
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1);
            assert.equal(overflow, false);
            assert.deepEqual(unknown, [], 'Unmocked mutations are forbidden');
            assert.deepEqual(errors, []);
            if (process.env.PW_ATTACHMENT_SCREENSHOTS) await page.screenshot({ path: `${process.env.PW_ATTACHMENT_SCREENSHOTS}/attachments-${shared ? 'shared' : 'personal'}-${width}.png` });
            console.log(`Attachments partial save, upload retry, processing state, download and layout passed: ${width}px ${shared ? 'shared' : 'personal'}`);
            await context.close();
        }
    }
} finally {
    await browser.close();
}
