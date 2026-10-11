import { flushPromises, mount } from '@vue/test-utils';
import { defineComponent, h, reactive } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useAccountTransactionFilters } from '@/modules/accounts/composables/useAccountTransactionFilters';
import { useTransactionFacilityFilters } from './useTransactionFacilityFilters';
const route = reactive<{ query: Record<string, unknown> }>({ query: {} });
const replace = vi.fn(({ query }: { query: Record<string, unknown> }) => {
    route.query = query;
    return Promise.resolve();
});
vi.mock('vue-router', () => ({ useRoute: () => route, useRouter: () => ({ replace }) }));
beforeEach(() => {
    route.query = {
        category_ids: '2,3',
        uncategorized: '1',
        has_attachments: '0',
        start_date: '2026-01-01',
        end_date: '2026-01-31',
    };
    replace.mockClear();
});
function render<T>(create: () => T): { state: T; unmount: () => void } {
    let state!: T;
    const wrapper = mount(
        defineComponent({
            setup() {
                state = create();
                return () => h('div');
            },
        }),
    );
    return { state, unmount: () => wrapper.unmount() };
}
describe('transaction filter URLs', () => {
    it('restores account filters and updates the URL when individual filters are removed', async () => {
        const { state, unmount } = render(useAccountTransactionFilters);
        expect(state.activeFilters.value).toMatchObject({
            categoryIds: ['2', '3'],
            uncategorized: true,
            hasAttachments: false,
        });
        state.selectedCategories.value = ['3'];
        state.hasAttachments.value = null;
        await flushPromises();
        expect(route.query).toMatchObject({
            category_ids: '3',
            uncategorized: undefined,
            has_attachments: undefined,
        });
        unmount();
    });
    it('preserves the mandatory global period with an explicit false filter', async () => {
        const { state, unmount } = render(useTransactionFacilityFilters);
        expect(state.activeFilters.value).toMatchObject({
            startDate: '2026-01-01',
            endDate: '2026-01-31',
            categoryIds: ['2', '3'],
            hasAttachments: false,
        });
        state.selectedCategories.value = ['uncategorized'];
        await flushPromises();
        expect(route.query.has_attachments).toBe('0');
        expect(route.query.category_ids).toBeUndefined();
        expect(route.query.uncategorized).toBe('1');
        state.startDate.value = null;
        expect(state.activeFilters.value).toBeNull();
        unmount();
    });
});
