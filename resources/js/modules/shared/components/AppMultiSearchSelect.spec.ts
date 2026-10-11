import { mount, flushPromises } from '@vue/test-utils';
import { defineComponent, ref } from 'vue';
import PrimeVue from 'primevue/config';
import { expect, it } from 'vitest';
import AppMultiSearchSelect from './AppMultiSearchSelect.vue';

it('searches categories, keeps multiple selections and clears to an empty array', async () => {
    const wrapper = mount(
        defineComponent({
            components: { AppMultiSearchSelect },
            setup() {
                return {
                    selected: ref(['uncategorized']),
                    options: [
                        { value: 'uncategorized', label: 'Sin categoría' },
                        { value: 'food', label: 'Comida · Personal' },
                        { value: 'travel', label: 'Viajes · Casa' },
                    ],
                };
            },
            template:
                '<AppMultiSearchSelect id="categories" v-model="selected" label="Categorías" search-placeholder="Buscar categoría" :options="options" />',
        }),
        { attachTo: document.body, global: { plugins: [PrimeVue] } },
    );
    try {
        await wrapper.get('.p-multiselect').trigger('click');
        await flushPromises();
        const search = document.querySelector<HTMLInputElement>(
            'input[placeholder="Buscar categoría"]',
        )!;
        search.value = 'Comida';
        search.dispatchEvent(new Event('input', { bubbles: true }));
        await flushPromises();
        const options = document.querySelectorAll<HTMLElement>('[role="option"]');
        expect(options).toHaveLength(1);
        expect(options[0]!.textContent).toContain('Comida');
        options[0]!.click();
        await flushPromises();
        expect(wrapper.vm.selected).toEqual(['uncategorized', 'food']);
        await wrapper.get('.p-multiselect-clear-icon').trigger('click');
        expect(wrapper.vm.selected).toEqual([]);
    } finally {
        wrapper.unmount();
    }
});
