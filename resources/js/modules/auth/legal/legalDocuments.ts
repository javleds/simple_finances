import { z } from 'zod';
import { useQuery } from '@tanstack/vue-query';
import { createApiClient } from '@/lib/api/apiClient';

const documentSchema = z.object({
    title: z.string(),
    version: z.string(),
    hash: z.string().regex(/^[a-f0-9]{64}$/),
    sections: z.array(z.object({ title: z.string(), paragraphs: z.array(z.string()) })),
});
export const legalDocumentsSchema = z.object({
    available: z.boolean(),
    provider: z.object({
        name: z.string(),
        rfc: z.string(),
        address: z.string(),
        email: z.string(),
        phone: z.string(),
        site_url: z.string(),
        brand: z.string(),
    }),
    documents: z.object({ terms: documentSchema, privacy: documentSchema }),
});
export type LegalDocuments = z.infer<typeof legalDocumentsSchema>;
export type LegalDocument = z.infer<typeof documentSchema>;
export type LegalAcceptanceInput = {
    termsAccepted: boolean;
    privacyPolicyAccepted: boolean;
    isAdult: boolean;
    termsHash: string;
    privacyHash: string;
};
const stateSchema = z.object({ required: z.boolean(), available: z.boolean() });
const api = createApiClient();
export async function getLegalDocuments(): Promise<LegalDocuments> {
    return z.object({ data: legalDocumentsSchema }).parse(await api.get('/legal-documents')).data;
}
export async function getLegalAcceptance(): Promise<z.infer<typeof stateSchema>> {
    return z.object({ data: stateSchema }).parse(await api.get('/legal-acceptance')).data;
}
export async function acceptLegalDocuments(input: LegalAcceptanceInput): Promise<void> {
    await api.post('/legal-acceptance', {
        terms_accepted: input.termsAccepted,
        privacy_policy_accepted: input.privacyPolicyAccepted,
        is_adult: input.isAdult,
        terms_hash: input.termsHash,
        privacy_hash: input.privacyHash,
    });
}
export function useLegalDocuments() {
    return useQuery({ queryKey: ['legal-documents'], queryFn: getLegalDocuments, staleTime: 0 });
}
export function safeLegalRedirect(value: unknown): string {
    if (
        typeof value !== 'string' ||
        !/^\/admin(?:\/|$|\?)/.test(value) ||
        value.startsWith('//') ||
        value.includes('\\') ||
        /[\u0000-\u0020]/.test(value)
    )
        return '/admin/dashboard';
    return value;
}
