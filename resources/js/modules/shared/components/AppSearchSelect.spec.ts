import { mount } from '@vue/test-utils';
import { defineComponent, nextTick } from 'vue';
import { describe, expect, it } from 'vitest';
import AppSearchSelect from './AppSearchSelect.vue';

const selectStub = defineComponent({
    name: 'PrimeSelectStub',
    props: ['options', 'disabled', 'loading'],
    emits: ['filter', 'hide', 'update:modelValue'],
    template: '<div />',
});

function render() {
    return mount(AppSearchSelect, {
        props: {
            id: 'category',
            modelValue: null,
            creatable: true,
            options: [{ value: 'food', label: 'Comida familiar' }],
        },
        global: { stubs: { Select: selectStub, Message: true } },
    });
}

describe('AppSearchSelect inline creation', () => {
    it('offers normalized creation and emits the name without replacing the selected value', async () => {
        const wrapper = render();
        const select = wrapper.getComponent(selectStub);
        select.vm.$emit('filter', { value: '  Ropa   nueva  ' });
        await nextTick();
        const option = select.props('options').at(-1);
        expect(option.label).toBe('Crear «Ropa nueva»');
        select.vm.$emit('update:modelValue', option.value);
        expect(wrapper.emitted('create')).toEqual([['Ropa nueva']]);
        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    });

    it('does not offer duplicates with different case or whitespace, or blank names', async () => {
        const wrapper = render();
        const select = wrapper.getComponent(selectStub);
        for (const value of [' COMIDA   FAMILIAR ', '   ']) {
            select.vm.$emit('filter', { value });
            await nextTick();
            expect(select.props('options')).toHaveLength(1);
        }
    });

    it('ignores a repeated creation selection while creation is pending', async () => {
        const wrapper = render();
        const select = wrapper.getComponent(selectStub);
        select.vm.$emit('filter', { value: 'Ropa' });
        await nextTick();
        const option = select.props('options').at(-1);
        select.vm.$emit('update:modelValue', option.value);
        await wrapper.setProps({ creating: true });
        const pendingSelect = wrapper.getComponent(selectStub);
        pendingSelect.vm.$emit('update:modelValue', option.value);
        expect(wrapper.emitted('create')).toHaveLength(1);
        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
        expect(pendingSelect.props('disabled')).toBe(true);
    });

    it('supports selecting existing categories and clearing the selection', () => {
        const wrapper = render();
        const select = wrapper.getComponent(selectStub);
        select.vm.$emit('update:modelValue', 'food');
        select.vm.$emit('update:modelValue', undefined);
        expect(wrapper.emitted('update:modelValue')).toEqual([['food'], [null]]);
        expect(wrapper.emitted('change')).toEqual([['food'], [null]]);
    });
});
