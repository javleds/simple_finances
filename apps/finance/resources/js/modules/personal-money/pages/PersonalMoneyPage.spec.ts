import { mount, flushPromises } from '@vue/test-utils';
import { ref } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import PersonalMoneyPage from './PersonalMoneyPage.vue';
import { createRouter, createMemoryHistory } from 'vue-router';
import type { PersonalMoneyState } from '../types';

const mocks = vi.hoisted(() => ({ saveSettings: vi.fn(), saveLink: vi.fn() }));
const state = ref<PersonalMoneyState>();
vi.mock('../composables/usePersonalMoney', () => ({
    usePersonalMoney: () => ({
        state,
        isLoading: ref(false),
        loadError: ref(null),
        mutationError: ref(null),
        isSaving: ref(false),
        page: ref(1),
        resolution: ref('all'),
        retry: vi.fn(),
        ...mocks,
    }),
}));
const global = {
    stubs: {
        AppCard: { template: '<section><slot /></section>' },
        AppButton: { template: '<button><slot /></button>' },
        AppTitle: { template: '<h2><slot /></h2>' },
        AppText: { template: '<p><slot /></p>' },
        AppListState: { template: '<div><slot /></div>' },
        AppSectionHeader: {
            template: '<section><slot name="actions"/><slot name="primary-metric"/></section>',
        },
        PersonalMoneySettingsModal: true,
        AppContextTabs: {
            name: 'AppContextTabs',
            props: ['modelValue', 'options'],
            template: '<div />',
        },
        AppActionMenu: {
            name: 'AppActionMenu',
            props: ['actions'],
            template: '<button>Acciones</button>',
        },
        AppModal: { props: ['open'], template: '<div v-if="open"><slot /></div>' },
        AppHeroMetric: { props: ['value'], template: '<p>{{ value }}</p>' },
    },
};
beforeEach(() => {
    vi.clearAllMocks();
    state.value = {
        settings: { account_id: 1, started_at: '2026-10-11', opening_balance: 100, enabled: true },
        accounts: [{ id: 1, name: 'Mis gastos' }],
        available_balance: 100,
        pending_count: 1,
        history: [
            {
                source_type: 'transaction',
                source_id: 4,
                account_name: 'Casa',
                concept: 'Mercado',
                occurred_at: '2026-10-11',
                amount: 50,
                effect: 0,
                kind: 'out_of_pocket',
                resolution: 'pending',
            },
        ],
        meta: { current_page: 1, last_page: 1, total: 1 },
    };
});

const wrappers: ReturnType<typeof mount>[] = [];
afterEach(() => {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
});
async function setup(url = '/personal-money') {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/personal-money', name: 'admin.personal-money', component: PersonalMoneyPage },
            { path: '/dashboard', name: 'admin.dashboard', component: { template: '<div />' } },
        ],
    });
    await router.push(url);
    await router.isReady();
    const wrapper = mount(PersonalMoneyPage, { global: { ...global, plugins: [router] } });
    wrappers.push(wrapper);
    return { wrapper, router };
}

describe('personal money page', () => {
    it('warns that unresolved sources make the available amount incomplete and allows resolution', async () => {
        const { wrapper } = await setup();
        expect(wrapper.text()).toContain('Tu disponible puede estar incompleto');
        expect(wrapper.text()).toContain('Mercado');
        const button = wrapper.findAll('button').find((item) => item.text() === 'Mi dinero');
        await button!.trigger('click');
        await flushPromises();
        expect(mocks.saveLink).toHaveBeenCalledWith({
            source_type: 'transaction',
            source_id: 4,
            resolution: 'personal',
        });
    });
    it('shows activation without personal balances when disabled', async () => {
        state.value!.settings!.enabled = false;
        state.value!.available_balance = null;
        state.value!.history = [];
        const { wrapper } = await setup();
        expect(wrapper.text()).toContain('Configurar mi disponible');
        expect(wrapper.find('[aria-label="Saldo disponible"]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('Mercado');
    });
    it('does not offer reclassification for native automatic movements', async () => {
        state.value!.history[0]!.resolution = 'automatic';
        const { wrapper } = await setup();
        expect(wrapper.findAll('button').some((item) => item.text() === 'Mi dinero')).toBe(false);
    });
    it('requires reconfiguration when the selected account becomes shared or unavailable', async () => {
        state.value!.account_available = false;
        state.value!.available_balance = null;
        const { wrapper } = await setup();
        expect(wrapper.text()).toContain('Configura otra cuenta individual');
        expect(wrapper.find('[aria-label="Saldo disponible"]').exists()).toBe(false);
    });
    it('displays the page clamped by the server', async () => {
        state.value!.meta.current_page = 2;
        state.value!.meta.last_page = 3;
        const { wrapper } = await setup();
        expect(wrapper.text()).toContain('Página 2 de 3');
    });
    it('opens pending review from a deep link and writes filter changes to the URL', async () => {
        const { wrapper, router } = await setup('/personal-money?resolution=pending');
        const tabs = wrapper.findComponent({ name: 'AppContextTabs' });
        expect(tabs.props('modelValue')).toBe('pending');
        tabs.vm.$emit('update:modelValue', 'all');
        await flushPromises();
        expect(router.currentRoute.value.query.resolution).toBeUndefined();
    });
    it('offers reclassification in a menu for resolved shared movements', async () => {
        state.value!.history[0]!.resolution = 'personal';
        const { wrapper } = await setup();
        const menu = wrapper.findComponent({ name: 'AppActionMenu' });
        expect(menu.exists()).toBe(true);
        menu.vm.$emit('action', 'external');
        await flushPromises();
        expect(mocks.saveLink).toHaveBeenCalledWith({
            source_type: 'transaction',
            source_id: 4,
            resolution: 'external',
        });
        expect(wrapper.findAll('button').some((button) => button.text() === 'Mi dinero')).toBe(
            false,
        );
    });
    it('keeps help behind an explicit action', async () => {
        const { wrapper } = await setup();
        expect(wrapper.text()).not.toContain('Al comenzar desde ahora');
        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Cómo se calcula')!
            .trigger('click');
        expect(wrapper.text()).toContain('Al comenzar desde ahora');
    });
});
