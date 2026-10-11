import { mount, flushPromises } from '@vue/test-utils';
import { ref } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PersonalMoneyPanel from './PersonalMoneyPanel.vue';
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

describe('personal money panel', () => {
    it('warns that unresolved sources make the available amount incomplete and allows resolution', async () => {
        const wrapper = mount(PersonalMoneyPanel, { global });
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
    it('shows activation without personal balances when disabled', () => {
        state.value!.settings!.enabled = false;
        state.value!.available_balance = null;
        state.value!.history = [];
        const wrapper = mount(PersonalMoneyPanel, { global });
        expect(wrapper.text()).toContain('Configurar mi disponible');
        expect(wrapper.find('[aria-label="Saldo disponible"]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('Mercado');
    });
    it('does not offer reclassification for native automatic movements', () => {
        state.value!.history[0]!.resolution = 'automatic';
        const wrapper = mount(PersonalMoneyPanel, { global });
        expect(wrapper.findAll('button').some((item) => item.text() === 'Mi dinero')).toBe(false);
    });
    it('requires reconfiguration when the selected account becomes shared or unavailable', () => {
        state.value!.account_available = false;
        state.value!.available_balance = null;
        const wrapper = mount(PersonalMoneyPanel, { global });
        expect(wrapper.text()).toContain('Configura otra cuenta individual');
        expect(wrapper.find('[aria-label="Saldo disponible"]').exists()).toBe(false);
    });
    it('displays the page clamped by the server', () => {
        state.value!.meta.current_page = 2;
        state.value!.meta.last_page = 3;
        const wrapper = mount(PersonalMoneyPanel, { global });
        expect(wrapper.text()).toContain('Página 2 de 3');
    });
});
