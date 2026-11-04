import { flushPromises, shallowMount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import RegisterPage from './RegisterPage.vue';
const mocks = vi.hoisted(() => ({
    form: undefined as unknown,
    legal: undefined as unknown,
    register: vi.fn(),
    submit: vi.fn(),
}));
vi.mock('vue-router', () => ({
    useRoute: () => ({ query: {} }),
    useRouter: () => ({ push: vi.fn() }),
}));
vi.mock('../composables/useRegisterForm', () => ({ useRegisterForm: () => mocks.form }));
vi.mock('../repositories/authRepository', () => ({
    createAuthRepository: () => ({ register: mocks.register }),
}));
vi.mock('../legal/legalDocuments', () => ({
    useLegalDocuments: () => ({ data: mocks.legal, isError: ref(false), refetch: vi.fn() }),
}));
vi.mock('@/modules/shared/composables/useFormFieldInteraction', () => ({
    useFormFieldInteraction: () => ({ error: ref(undefined), touch: vi.fn() }),
}));
let resolveValidation: (value: unknown) => void;
beforeEach(() => {
    vi.clearAllMocks();
    mocks.submit.mockImplementation(
        () =>
            new Promise((resolve) => {
                resolveValidation = resolve;
            }),
    );
    mocks.form = {
        name: ref('Test'),
        email: ref('test@example.com'),
        password: ref('password123'),
        passwordConfirmation: ref('password123'),
        termsAccepted: ref(true),
        privacyPolicyAccepted: ref(true),
        isAdult: ref(true),
        isSubmitting: ref(false),
        isSubmitDisabled: ref(false),
        submitForm: mocks.submit,
    };
    mocks.legal = ref({
        available: true,
        documents: { terms: { hash: 'a'.repeat(64) }, privacy: { hash: 'b'.repeat(64) } },
    });
});
function mountPage() {
    return shallowMount(RegisterPage, {
        global: { stubs: { AppCard: { template: '<div><slot /></div>' } } },
    });
}
describe('registration consent validation races', () => {
    it('refuses old checked declarations after documents change during validation', async () => {
        const page = mountPage();
        await page.get('form').trigger('submit');
        (mocks.legal as ReturnType<typeof ref>).value = {
            available: true,
            documents: { terms: { hash: 'c'.repeat(64) }, privacy: { hash: 'b'.repeat(64) } },
        };
        await flushPromises();
        resolveValidation({ termsAccepted: true, privacyPolicyAccepted: true, isAdult: true });
        await flushPromises();
        expect(mocks.register).not.toHaveBeenCalled();
        page.unmount();
    });
    it('ignores a second submission while validation is pending', async () => {
        const page = mountPage();
        await page.get('form').trigger('submit');
        await page.get('form').trigger('submit');
        expect(mocks.submit).toHaveBeenCalledOnce();
        resolveValidation(undefined);
        await flushPromises();
        expect(mocks.register).not.toHaveBeenCalled();
        page.unmount();
    });
});
