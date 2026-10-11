import { flushPromises, mount } from '@vue/test-utils';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import { defineComponent } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useCategories } from './useCategories';
import type { CategoryCatalog } from '../types';

const repository = vi.hoisted(() => ({ list: vi.fn(), create: vi.fn() }));
vi.mock('../repositories/categoriesRepository', () => ({
    createCategoriesRepository: () => repository,
}));

const catalog: CategoryCatalog = {
    categories: [],
    scope: 'personal',
    accountId: null,
    accountName: null,
    canManage: true,
};

function renderCategories() {
    let state!: ReturnType<typeof useCategories>;
    const wrapper = mount(
        defineComponent({
            setup() {
                state = useCategories();
                return () => null;
            },
        }),
        {
            global: {
                plugins: [
                    [
                        VueQueryPlugin,
                        {
                            queryClient: new QueryClient({
                                defaultOptions: { queries: { retry: false } },
                            }),
                        },
                    ],
                ],
            },
        },
    );
    return { state, wrapper };
}

describe('category creation from the selector', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        repository.list.mockResolvedValue(catalog);
    });

    it('returns the created category and refreshes catalog queries', async () => {
        const category = {
            id: '1',
            name: 'Comida',
            accountId: null,
            scope: 'personal',
            transactionsCount: 0,
            canManage: true,
        };
        repository.create.mockResolvedValue(category);
        const { state, wrapper } = renderCategories();
        await flushPromises();
        expect(await state.createCategory('Comida')).toEqual(category);
        expect(repository.list).toHaveBeenCalledTimes(2);
        expect(state.isCreating.value).toBe(false);
        wrapper.unmount();
    });

    it('recovers the existing category when another member creates the same name', async () => {
        const category = {
            id: '2',
            name: 'Comida familiar',
            accountId: null,
            scope: 'personal',
            transactionsCount: 0,
            canManage: true,
        };
        repository.create.mockRejectedValue(new Error('Duplicate'));
        const { state, wrapper } = renderCategories();
        await flushPromises();
        repository.list.mockResolvedValue({ ...catalog, categories: [category] });
        expect(await state.createCategory(' Comida   FAMILIAR ')).toEqual(category);
        expect(state.createError.value).toBeNull();
        wrapper.unmount();
    });

    it('blocks repeat submissions while creating and exposes failures', async () => {
        let reject!: (error: Error) => void;
        repository.create.mockReturnValue(
            new Promise((_, rejectPromise) => {
                reject = rejectPromise;
            }),
        );
        const { state, wrapper } = renderCategories();
        await flushPromises();
        const first = state.createCategory('Comida');
        expect(await state.createCategory('Comida')).toBeNull();
        expect(repository.create).toHaveBeenCalledTimes(1);
        reject(new Error('Network failure'));
        expect(await first).toBeNull();
        expect(state.createError.value).toBeTruthy();
        expect(state.isCreating.value).toBe(false);
        wrapper.unmount();
    });
});
