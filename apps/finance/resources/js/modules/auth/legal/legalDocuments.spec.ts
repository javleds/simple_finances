import { afterEach, describe, expect, it, vi } from 'vitest';
import { acceptLegalDocuments, getLegalDocuments, safeLegalRedirect } from './legalDocuments';
import { registerFormSchema } from '../schemas/authSchemas';

afterEach(() => vi.unstubAllGlobals());
describe('legal consent contracts', () => {
    it('sends the displayed revisions and explicit declarations', async () => {
        const fetch = vi.fn().mockResolvedValue(
            new Response(JSON.stringify({ data: { required: false, available: true } }), {
                headers: { 'content-type': 'application/json' },
            }),
        );
        vi.stubGlobal('fetch', fetch);
        await acceptLegalDocuments({
            termsAccepted: true,
            privacyPolicyAccepted: true,
            isAdult: true,
            termsHash: 'a'.repeat(64),
            privacyHash: 'b'.repeat(64),
        });
        expect(JSON.parse(fetch.mock.calls[0]![1].body)).toEqual({
            terms_accepted: true,
            privacy_policy_accepted: true,
            is_adult: true,
            terms_hash: 'a'.repeat(64),
            privacy_hash: 'b'.repeat(64),
        });
    });
    it('rejects malformed canonical documents', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue(
                new Response(JSON.stringify({ data: { available: true } }), {
                    headers: { 'content-type': 'application/json' },
                }),
            ),
        );
        await expect(getLegalDocuments()).rejects.toThrow();
    });
    it('requires adulthood independently of both acceptances', () => {
        const input = {
            name: 'Test',
            email: 'a@example.com',
            phoneNumber: '',
            password: 'password123',
            passwordConfirmation: 'password123',
            termsAccepted: true,
            privacyPolicyAccepted: true,
            isAdult: false,
        };
        expect(registerFormSchema.safeParse(input).success).toBe(false);
        expect(registerFormSchema.safeParse({ ...input, isAdult: true }).success).toBe(true);
    });
    it('preserves internal invitation destinations and refuses external paths', () => {
        expect(safeLegalRedirect('/admin/invitations?token=example')).toBe(
            '/admin/invitations?token=example',
        );
        for (const value of [
            'https://evil.test',
            '//evil.test',
            '/administrator',
            '/admin\\evil',
            '/admin\n/evil',
            undefined,
        ]) {
            expect(safeLegalRedirect(value)).toBe('/admin/dashboard');
        }
    });
});
