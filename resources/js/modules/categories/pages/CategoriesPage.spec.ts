import { flushPromises, mount } from '@vue/test-utils';
import { defineComponent, ref } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import CategoriesPage from './CategoriesPage.vue';

const mocks = vi.hoisted(() => ({ refresh: vi.fn(), remove: vi.fn(), invalidate: vi.fn() }));
vi.mock('@tanstack/vue-query', () => ({
    useQuery: () => ({ data: ref([]), isError: ref(false), refetch: vi.fn() }),
    useQueryClient: () => ({ invalidateQueries: mocks.invalidate }),
}));
vi.mock('../repositories/categoriesRepository', () => ({
    createCategoriesRepository: () => ({ remove: mocks.remove }),
}));
const category = {
    id: '1',
    name: 'Comida',
    scope: 'shared',
    accountId: '4',
    transactionsCount: 0,
    canManage: true,
};
vi.mock('../composables/useCategories', () => ({
    categoryQueryKeys: { all: ['categories'] },
    useCategories: () => ({
        categories: ref([category]),
        scope: ref('shared'),
        accountName: ref('Casa'),
        isLoading: ref(false),
        error: ref(null),
        refresh: mocks.refresh,
    }),
}));
const modal = defineComponent({
    props: ['open', 'actions'],
    emits: ['action'],
    template:
        '<div v-if="open" data-modal><slot /><button data-confirm :disabled="actions[1].disabled" @click="$emit(\'action\', \'delete\')">Eliminar</button></div>',
});
const selector = defineComponent({
    props: ['id', 'modelValue', 'options'],
    emits: ['update:modelValue'],
    template:
        '<select :id="id" :value="modelValue" @change="$emit(\'update:modelValue\', $event.target.value)"><option value="">Seleccionar</option><option v-for="option in options" :value="option.value">{{ option.label }}</option></select>',
});

function renderPage() {
    return mount(CategoriesPage, {
        global: {
            stubs: {
                AppModal: modal,
                AppSearchSelect: selector,
                AppActionMenu: defineComponent({
                    emits: ['delete'],
                    template:
                        '<button data-open-delete @click="$emit(\'delete\')">Eliminar</button>',
                }),
                AppListState: { template: '<div><slot /></div>' },
                AppSectionHeader: { template: '<div><slot name="actions" /></div>' },
                AppButton: { template: '<button><slot /></button>' },
                AppInput: true,
                AppEmptyState: true,
                Message: { template: '<div><slot /></div>' },
            },
        },
    });
}

describe('category deletion decisions', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        category.transactionsCount = 0;
        mocks.refresh.mockResolvedValue({ data: { categories: [category] }, error: null });
        mocks.remove.mockResolvedValue(undefined);
    });

    it('sends no reassignment decision for a category with no movements', async () => {
        const wrapper = renderPage();
        await wrapper.get('[data-open-delete]').trigger('click');
        await flushPromises();
        await wrapper.get('[data-confirm]').trigger('click');
        await flushPromises();
        expect(mocks.remove).toHaveBeenCalledWith(null, '1', {
            action: undefined,
            targetCategoryId: null,
        });
    });

    it('requires an explicit decision when the category is used', async () => {
        category.transactionsCount = 3;
        const wrapper = renderPage();
        await wrapper.get('[data-open-delete]').trigger('click');
        await flushPromises();
        expect(wrapper.get('[data-confirm]').attributes('disabled')).toBeDefined();
        expect(wrapper.text()).toContain('otros miembros');
        await wrapper.get('#category-delete-action').setValue('uncategorize');
        await wrapper.get('[data-confirm]').trigger('click');
        await flushPromises();
        expect(mocks.remove).toHaveBeenCalledWith(null, '1', {
            action: 'uncategorize',
            targetCategoryId: null,
        });
    });

    it('shows a refresh failure without allowing deletion', async () => {
        mocks.refresh.mockResolvedValue({
            error: new Error('No se pudo verificar'),
            data: { categories: [category] },
        });
        const wrapper = renderPage();
        await wrapper.get('[data-open-delete]').trigger('click');
        await flushPromises();
        expect(wrapper.text()).toContain('No se pudo verificar');
        expect(wrapper.find('[data-modal]').exists()).toBe(false);
        expect(mocks.remove).not.toHaveBeenCalled();
    });

    it('ignores a delete preparation completed after switching catalogs', async () => {
        let resolve!: (value: unknown) => void;
        mocks.refresh.mockReturnValue(
            new Promise((resolvePromise) => {
                resolve = resolvePromise;
            }),
        );
        const wrapper = renderPage();
        await wrapper.get('[data-open-delete]').trigger('click');
        wrapper.findComponent(selector).vm.$emit('update:modelValue', '4');
        await flushPromises();
        resolve({ data: { categories: [category] }, error: null });
        await flushPromises();
        expect(wrapper.find('[data-modal]').exists()).toBe(false);
    });
});
