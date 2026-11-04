import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { ApiError, clearStoredAuthToken, createApiClient, setStoredAuthToken } from './apiClient';

beforeEach(() => {
    clearStoredAuthToken();
    vi.stubEnv('VITE_API_BASE_URL', '');
});

afterEach(() => {
    vi.unstubAllEnvs();
    vi.unstubAllGlobals();
});

describe('integrated API transport', () => {
    it('preserves authentication and the destination when current legal acceptance is required', async () => {
        const assign = vi.fn();
        const localStorage = window.localStorage;
        vi.stubGlobal('window', {
            localStorage,
            location: {
                assign,
                pathname: '/admin/accounts/4',
                search: '?tab=ledger',
                hash: '#last',
            },
        });
        setStoredAuthToken('current-token');
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue(
                new Response(
                    JSON.stringify({
                        code: 'legal_acceptance_required',
                        message: 'Revisa los documentos.',
                    }),
                    { status: 428, headers: { 'Content-Type': 'application/json' } },
                ),
            ),
        );

        await expect(createApiClient().get('/accounts/4')).rejects.toMatchObject({ status: 428 });

        expect(assign).toHaveBeenCalledWith(
            '/auth/legal-acceptance?redirect=%2Fadmin%2Faccounts%2F4%3Ftab%3Dledger%23last',
        );
        expect(localStorage.getItem('finsi_20_auth_token')).toBe('current-token');
    });

    it('does not redirect repeatedly while reviewing legal documents', async () => {
        const assign = vi.fn();
        vi.stubGlobal('window', {
            localStorage: window.localStorage,
            location: { assign, pathname: '/auth/legal-acceptance', search: '', hash: '' },
        });
        setStoredAuthToken('current-token');
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue(
                new Response(JSON.stringify({ code: 'legal_acceptance_required' }), {
                    status: 428,
                    headers: { 'Content-Type': 'application/json' },
                }),
            ),
        );

        await expect(createApiClient().get('/accounts')).rejects.toMatchObject({ status: 428 });
        expect(assign).not.toHaveBeenCalled();
    });

    it('uses the Laravel API on the current origin by default', async () => {
        const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
            new Response('{}', {
                headers: { 'Content-Type': 'application/json' },
            }),
        );
        vi.stubGlobal('fetch', fetchMock);

        await createApiClient().get('/profile');

        expect(fetchMock).toHaveBeenCalledWith(
            '/api/profile',
            expect.objectContaining({
                headers: { Accept: 'application/json' },
            }),
        );
    });

    it('preserves explicit API origins and Bearer authentication', async () => {
        vi.stubEnv('VITE_API_BASE_URL', 'https://api.example.com/api/');
        setStoredAuthToken('test-token');
        const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
            new Response('{}', {
                headers: { 'Content-Type': 'application/json' },
            }),
        );
        vi.stubGlobal('fetch', fetchMock);

        await createApiClient().get('profile');

        expect(fetchMock).toHaveBeenCalledWith(
            'https://api.example.com/api/profile',
            expect.objectContaining({
                headers: { Accept: 'application/json', Authorization: 'Bearer test-token' },
            }),
        );
    });

    it('redirects expired sessions to the application login, independent of asset base', async () => {
        vi.stubEnv('BASE_URL', '/build/');
        const assign = vi.fn<(url: string) => void>();
        const localStorage = window.localStorage;
        vi.stubGlobal('window', { localStorage, location: { assign } });
        setStoredAuthToken('expired-token');
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue(
                new Response('{"message":"Unauthenticated."}', {
                    status: 401,
                    headers: { 'Content-Type': 'application/json' },
                }),
            ),
        );

        await expect(createApiClient().get('/profile')).rejects.toBeInstanceOf(ApiError);

        expect(assign).toHaveBeenCalledWith('/auth');
        expect(localStorage.getItem('finsi_20_auth_token')).toBeNull();
    });
});

describe('private attachment transport', () => {
    it('sends multipart without overwriting its boundary and retains authentication', async () => {
        setStoredAuthToken('file-token');
        const fetchMock = vi
            .fn<typeof fetch>()
            .mockResolvedValue(
                new Response('{}', { headers: { 'Content-Type': 'application/json' } }),
            );
        vi.stubGlobal('fetch', fetchMock);
        const body = new FormData();
        body.append('file', new File(['receipt'], 'receipt.pdf'));
        await createApiClient().post('/attachments', body);
        const options = fetchMock.mock.calls[0]?.[1];
        expect(options?.body).toBe(body);
        expect(options?.headers).toEqual({
            Accept: 'application/json',
            Authorization: 'Bearer file-token',
        });
    });
    it('downloads binary bytes and preserves structured API errors', async () => {
        setStoredAuthToken('file-token');
        const fetchMock = vi
            .fn<typeof fetch>()
            .mockResolvedValueOnce(
                new Response('receipt', { headers: { 'Content-Type': 'application/pdf' } }),
            )
            .mockResolvedValueOnce(
                new Response('{"message":"Access denied"}', {
                    status: 403,
                    headers: { 'Content-Type': 'application/json' },
                }),
            );
        vi.stubGlobal('fetch', fetchMock);
        const file = await createApiClient().download('/attachments/1/download');
        expect(await file.text()).toBe('receipt');
        await expect(createApiClient().download('/attachments/2/download')).rejects.toMatchObject({
            status: 403,
            message: 'Access denied',
        });
    });
});
