import { mount } from '@vue/test-utils';
import { ref } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
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
        AppLink: { name: 'AppLink', props: ['to'], template: '<a><slot /></a>' },
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
function setup() {
    const wrapper = mount(PersonalMoneyPanel, { global });
    wrappers.push(wrapper);
    return wrapper;
}

describe('personal money home summary', () => {
    it('shows the amount and direct links without rendering history or settings', () => {
        const wrapper = setup();
        expect(wrapper.text()).toContain('$100.00');
        expect(wrapper.text()).toContain('Mis gastos');
        expect(wrapper.text()).toContain('1 movimientos por revisar');
        expect(wrapper.text()).not.toContain('Mercado');
        expect(wrapper.find('form').exists()).toBe(false);
        const links = wrapper.findAllComponents({ name: 'AppLink' });
        expect(links[0]!.props('to')).toEqual({ name: 'admin.personal-money' });
        expect(links[1]!.props('to')).toEqual({
            name: 'admin.personal-money',
            query: { resolution: 'pending' },
        });
    });
    it('shows a configuration link while disabled without exposing balances or history', () => {
        state.value!.settings!.enabled = false;
        const wrapper = setup();
        expect(wrapper.text()).toContain('Configurar mi disponible');
        expect(wrapper.text()).not.toContain('$100.00');
        expect(wrapper.text()).not.toContain('Mercado');
        expect(wrapper.find('form').exists()).toBe(false);
    });
    it('asks for reconfiguration when the selected account is unavailable', () => {
        state.value!.account_available = false;
        state.value!.available_balance = null;
        const wrapper = setup();
        expect(wrapper.text()).toContain('Configura otra cuenta individual');
        expect(wrapper.find('[aria-label="Saldo disponible"]').exists()).toBe(false);
    });
});
