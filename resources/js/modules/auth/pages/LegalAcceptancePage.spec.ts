import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import { ApiError } from '@/lib/api/apiClient';
import LegalAcceptancePage from './LegalAcceptancePage.vue';
const mocks = vi.hoisted(() => ({
    accept: vi.fn(),
    refetch: vi.fn(),
    replace: vi.fn(),
    documents: undefined as unknown,
}));
vi.mock('vue-router', () => ({
    useRoute: () => ({ query: { redirect: '/admin/invitations' } }),
    useRouter: () => ({ replace: mocks.replace }),
}));
vi.mock('../legal/legalDocuments', async (importOriginal) => {
    const original = await importOriginal<Record<string, unknown>>();
    return {
        ...original,
        acceptLegalDocuments: mocks.accept,
        useLegalDocuments: () => ({
            data: ref(mocks.documents),
            isError: ref(false),
            refetch: mocks.refetch,
        }),
    };
});
const fieldStub = {
    props: ['termsAccepted', 'privacyPolicyAccepted', 'isAdult'],
    emits: ['update:termsAccepted', 'update:privacyPolicyAccepted', 'update:isAdult'],
    template: `<button type="button" data-test="consent" @click="$emit('update:termsAccepted', true); $emit('update:privacyPolicyAccepted', true); $emit('update:isAdult', true)">Consent</button>`,
};
function mountPage() {
    return mount(LegalAcceptancePage, {
        global: {
            stubs: {
                RegisterLegalFields: fieldStub,
                RegisterLegalPreview: true,
                AuthFeedbackBanner: {
                    props: ['message'],
                    template: '<p role="alert">{{ message }}</p>',
                },
                AppCard: { template: '<div><slot /></div>' },
                AppText: { template: '<p><slot /></p>' },
                AppTitle: { template: '<h1><slot /></h1>' },
                AppButton: {
                    props: ['disabled', 'loading'],
                    template: '<button :disabled="disabled"><slot /></button>',
                },
            },
        },
    });
}
beforeEach(() => {
    vi.clearAllMocks();
    mocks.documents = {
        available: true,
        documents: { terms: { hash: 'a'.repeat(64) }, privacy: { hash: 'b'.repeat(64) } },
    };
    mocks.accept.mockResolvedValue(undefined);
});
describe('legal acceptance recovery', () => {
    it('resets declarations and fetches current documents after a stale revision', async () => {
        mocks.accept.mockRejectedValue(new ApiError('Los documentos cambiaron.', 409));
        const page = mountPage();
        await page.get('[data-test="consent"]').trigger('click');
        await page.get('form').trigger('submit');
        await flushPromises();
        expect(mocks.refetch).toHaveBeenCalledOnce();
        expect(page.findComponent(fieldStub).props()).toMatchObject({
            termsAccepted: false,
            privacyPolicyAccepted: false,
            isAdult: false,
        });
        expect(page.text()).toContain('Los documentos cambiaron.');
        expect(mocks.replace).not.toHaveBeenCalled();
        page.unmount();
    });
    it('blocks consent while legal information is unavailable', async () => {
        mocks.documents = {
            available: false,
            documents: { terms: { hash: '' }, privacy: { hash: '' } },
        };
        const page = mountPage();
        await page.get('[data-test="consent"]').trigger('click');
        await page.get('form').trigger('submit');
        expect(mocks.accept).not.toHaveBeenCalled();
        expect(page.text()).toContain('no está disponible');
        page.unmount();
    });
    it('continues to the preserved invitation after saving acceptance', async () => {
        const page = mountPage();
        await page.get('[data-test="consent"]').trigger('click');
        await page.get('form').trigger('submit');
        await flushPromises();
        expect(mocks.replace).toHaveBeenCalledWith('/admin/invitations');
        page.unmount();
    });
});
