import { beforeEach, describe, expect, it, vi } from 'vitest';
const mocks = vi.hoisted(() => ({
    guard: undefined as unknown,
    token: 'token' as string | null,
    required: true,
    fail: false,
}));
vi.mock('vue-router', () => ({
    createWebHistory: vi.fn(),
    createRouter: () => ({
        beforeEach: (guard: unknown) => {
            mocks.guard = guard;
        },
    }),
}));
vi.mock('@/lib/api/apiClient', () => ({ getStoredAuthToken: () => mocks.token }));
vi.mock('@/modules/auth/lib/authSession', () => ({
    getStoredAuthSession: () => ({ user: { isEmailVerified: true } }),
    getPendingVerificationEmail: () => null,
}));
vi.mock('@/modules/auth/legal/legalDocuments', () => ({
    getLegalAcceptance: async () => {
        if (mocks.fail) throw new Error('offline');
        return { available: true, required: mocks.required };
    },
}));
import './index';
const guard = (name: string, fullPath = '/admin/invitations?token=example') =>
    (mocks.guard as (to: unknown) => Promise<unknown>)({ name, fullPath, query: {} });
beforeEach(() => {
    mocks.token = 'token';
    mocks.required = true;
    mocks.fail = false;
});
describe('current legal acceptance navigation', () => {
    it('allows authenticated users to read public legal pages', async () => {
        expect(await guard('auth.privacy')).toBe(true);
        expect(await guard('auth.terms')).toBe(true);
        expect(await guard('auth.password-recovery')).toBe(true);
        expect(await guard('auth.password-reset')).toBe(true);
    });
    it('gates returning users while retaining their destination', async () => {
        expect(await guard('admin.invitations')).toEqual({
            name: 'auth.legal-acceptance',
            query: { redirect: '/admin/invitations?token=example' },
        });
        expect(await guard('auth.legal-acceptance')).toBe(true);
    });
    it('requires a recoverable decision when status cannot be retrieved', async () => {
        mocks.fail = true;
        expect(await guard('admin.dashboard')).toMatchObject({ name: 'auth.legal-acceptance' });
    });
    it('continues only when current acceptance is confirmed', async () => {
        mocks.required = false;
        expect(await guard('admin.dashboard')).toBe(true);
        mocks.token = null;
        expect(await guard('auth.legal-acceptance')).toEqual({ name: 'auth.login' });
    });
});
