import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import PrimeVue from 'primevue/config';
import { ref } from 'vue';
import ConfigPage from './ConfigPage.vue';
import type { WhatsappConnection } from '../repositories/whatsappConnectionRepository';

const repository = vi.hoisted(() => ({
    get: vi.fn<() => Promise<WhatsappConnection>>(),
}));

vi.mock('../repositories/whatsappConnectionRepository', () => ({
    createWhatsappConnectionRepository: () => repository,
}));
vi.mock('../composables/useNotificationSettings', () => ({
    useNotificationSettings: () => ({
        globalNotificationSettings: ref([]),
        accountNotificationSettings: ref([]),
        isLoading: ref(false),
        isSaving: ref(false),
        saveError: ref(null),
        toggleGlobalSetting: vi.fn(),
        toggleAccountSetting: vi.fn(),
    }),
}));

let wrapper: VueWrapper;
let queryClient: QueryClient;
let meta: HTMLMetaElement | undefined;

async function render(availability?: string): Promise<void> {
    if (availability !== undefined) {
        meta = document.createElement('meta');
        meta.name = 'app-whatsapp-enabled';
        meta.content = availability;
        document.head.append(meta);
    }
    queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    wrapper = mount(ConfigPage, {
        global: {
            stubs: { RouterLink: true, teleport: true },
            plugins: [
                [VueQueryPlugin, { queryClient }],
                [PrimeVue, { unstyled: true }],
            ],
        },
    });
    await flushPromises();
}

beforeEach(() => {
    vi.resetAllMocks();
    window.localStorage.clear();
    repository.get.mockResolvedValue({
        status: 'unlinked',
        phone_number: null,
        expires_at: null,
        resend_available_at: null,
    });
});
afterEach(() => {
    wrapper?.unmount();
    queryClient?.clear();
    meta?.remove();
    meta = undefined;
});

describe('WhatsApp availability in settings', () => {
    it.each([undefined, 'false', 'invalid'])(
        'hides linking without fetching when availability is %s',
        async (availability) => {
            await render(availability);

            expect(wrapper.text()).not.toContain('WhatsApp');
            expect(repository.get).not.toHaveBeenCalled();
            expect(wrapper.text()).toContain('Configuración de notificaciones');
            expect(wrapper.text()).toContain('Utilidades');
        },
    );

    it('shows the connection and fetches its state when enabled', async () => {
        await render('true');

        expect(wrapper.text()).toContain('Ligar cuenta con WhatsApp');
        expect(repository.get).toHaveBeenCalledOnce();
    });
});
