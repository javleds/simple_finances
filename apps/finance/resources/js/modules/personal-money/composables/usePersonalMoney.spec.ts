import { defineComponent, h } from 'vue';
import { mount, flushPromises } from '@vue/test-utils';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { usePersonalMoney } from './usePersonalMoney';

const repository = vi.hoisted(() => ({ get: vi.fn(), saveSettings: vi.fn(), saveLink: vi.fn() }));
vi.mock('../repositories/personalMoneyRepository', () => ({ personalMoneyRepository: repository }));
let result: ReturnType<typeof usePersonalMoney>;
function setup() {
    const client = new QueryClient({
        defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
    });
    const wrapper = mount(
        defineComponent({
            setup() {
                result = usePersonalMoney();
                return () => h('div');
            },
        }),
        { global: { plugins: [[VueQueryPlugin, { queryClient: client }]] } },
    );
    return wrapper;
}
beforeEach(() => {
    vi.clearAllMocks();
    repository.get.mockResolvedValue({
        settings: null,
        accounts: [],
        available_balance: null,
        pending_count: 0,
        history: [],
        meta: { current_page: 1, last_page: 3, total: 50 },
    });
    repository.saveLink.mockResolvedValue({});
});

describe('personal money query state', () => {
    it('resets pagination when switching to unresolved sources', async () => {
        const wrapper = setup();
        await flushPromises();
        result.page.value = 2;
        await flushPromises();
        expect(repository.get).toHaveBeenCalledWith(2, 'all');
        result.resolution.value = 'pending';
        await flushPromises();
        expect(result.page.value).toBe(1);
        expect(repository.get).toHaveBeenLastCalledWith(1, 'pending');
        wrapper.unmount();
    });
    it('refetches the active balance after source resolution', async () => {
        const wrapper = setup();
        await flushPromises();
        repository.get.mockClear();
        await result.saveLink({ source_type: 'transaction', source_id: 8, resolution: 'personal' });
        await flushPromises();
        expect(repository.get).toHaveBeenCalledWith(1, 'all');
        wrapper.unmount();
    });
});
