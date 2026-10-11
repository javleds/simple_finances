import { flushPromises, mount } from '@vue/test-utils';
import { defineComponent, ref } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Category } from '@/modules/categories/types';
import TransactionCategorySelect from './TransactionCategorySelect.vue';

const mocks = vi.hoisted(() => ({ useCategories: vi.fn() }));
vi.mock('@/modules/categories/composables/useCategories', () => mocks);

const selectorStub = defineComponent({
    name: 'AppSearchSelect',
    props: ['modelValue', 'options', 'error', 'creating'],
    emits: ['create', 'update:modelValue'],
    template: '<div>{{ error }}</div>',
});

const category: Category = {
    id: 'food',
    name: 'Comida',
    accountId: null,
    scope: 'personal',
    transactionsCount: 0,
    canManage: true,
};

function catalog() {
    return {
        categories: ref<Category[]>([category]),
        scope: ref<'personal' | 'shared'>('personal'),
        accountName: ref<string | null>(null),
        isLoading: ref(false),
        error: ref<string | null>(null),
        isCreating: ref(false),
        createError: ref<string | null>(null),
        createCategory: vi.fn().mockResolvedValue(category),
        refresh: vi.fn().mockResolvedValue({ data: { categories: [category] }, error: null }),
    };
}

let state: ReturnType<typeof catalog>;
beforeEach(() => {
    state = catalog();
    mocks.useCategories.mockReturnValue(state);
});

function render() {
    return mount(TransactionCategorySelect, {
        props: { accountId: 'first', modelValue: 'food' },
        global: {
            stubs: {
                AppSearchSelect: selectorStub,
                AppText: { template: '<p><slot /></p>' },
                AppButton: true,
            },
        },
    });
}

describe('TransactionCategorySelect', () => {
    it('selects a category created inline', async () => {
        const wrapper = render();
        wrapper.getComponent(selectorStub).vm.$emit('create', 'Comida');
        await flushPromises();
        expect(state.createCategory).toHaveBeenCalledWith('Comida');
        expect(wrapper.emitted('update:modelValue')).toEqual([['food']]);
    });

    it('keeps the existing selection and exposes an inline creation error', async () => {
        state.createCategory.mockImplementation(async () => {
            state.createError.value = 'No fue posible crear la categoría.';
            return null;
        });
        const wrapper = render();
        wrapper.getComponent(selectorStub).vm.$emit('create', 'Ropa');
        await flushPromises();
        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
        expect(wrapper.getComponent(selectorStub).props('modelValue')).toBe('food');
        expect(wrapper.text()).toContain('No fue posible crear la categoría.');
    });

    it('clears categories unavailable in the new account and keeps compatible categories', async () => {
        const wrapper = render();
        await wrapper.setProps({ accountId: 'second' });
        await flushPromises();
        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
        state.refresh.mockResolvedValue({ data: { categories: [] }, error: null });
        await wrapper.setProps({ accountId: 'third' });
        await flushPromises();
        expect(wrapper.emitted('update:modelValue')).toEqual([[null]]);
    });

    it('does not apply a completed creation to a different account', async () => {
        let resolveCreation!: (value: Category) => void;
        state.createCategory.mockReturnValue(
            new Promise<Category>((resolve) => {
                resolveCreation = resolve;
            }),
        );
        const wrapper = render();
        wrapper.getComponent(selectorStub).vm.$emit('create', 'Comida');
        await wrapper.setProps({ accountId: 'second' });
        resolveCreation(category);
        await flushPromises();
        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    });

    it('identifies a shared catalog and propagates busy state', async () => {
        state.scope.value = 'shared';
        state.accountName.value = 'Casa';
        const wrapper = render();
        expect(wrapper.text()).toContain('Compartida · Casa');
        expect(wrapper.text()).toContain('todos los miembros');
        state.isCreating.value = true;
        await flushPromises();
        expect(wrapper.emitted('busy')).toEqual([[false], [true]]);
        expect(wrapper.getComponent(selectorStub).props('creating')).toBe(true);
    });
});
