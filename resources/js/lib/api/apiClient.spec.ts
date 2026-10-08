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
    it('uses the Laravel API on the current origin by default', async () => {
        const fetchMock = vi.fn().mockResolvedValue(
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
        const fetchMock = vi.fn().mockResolvedValue(
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
        const assign = vi.fn();
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
