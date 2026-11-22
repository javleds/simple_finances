import { mount } from '@vue/test-utils';
import { defineComponent } from 'vue';
import { describe, expect, it } from 'vitest';
import AppPercentageSplitEditor from './AppPercentageSplitEditor.vue';

const input = defineComponent({
    props: ['modelValue'],
    emits: ['update:modelValue'],
    template:
        '<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
});
function render(amount = '0.00') {
    return mount(AppPercentageSplitEditor, {
        props: {
            users: [],
            modelValue: {},
            monetary: {
                total: '100000.00',
                amount,
                maximum: '100000.00',
                source: 'Javi',
                recipient: 'Mercadopago',
            },
        },
        global: { stubs: { AppInput: input, Button: { template: '<button><slot /></button>' } } },
    });
}

describe('monetary split editor', () => {
    it('does not round an input with excess precision', async () => {
        const wrapper = render();
        await wrapper.get('input').setValue('0.015');
        await wrapper.get('input').trigger('blur');
        expect(wrapper.emitted('update:amount')).toBeUndefined();
        expect(wrapper.get('input').element.value).toBe('0.015');
    });
    it('preserves exact cents even when the percentage cannot represent them', async () => {
        const wrapper = render();
        await wrapper.get('input').setValue('123.45');
        await wrapper.get('input').trigger('blur');
        expect(wrapper.emitted('update:amount')?.[0]).toEqual(['123.45']);
        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    });
    it('caps amounts to the available remainder and supports keyboard cents', async () => {
        const wrapper = render('12.34');
        await wrapper.setProps({
            monetary: {
                total: '100000.00',
                amount: '12.34',
                maximum: '24.56',
                source: 'Javi',
                recipient: 'Mercadopago',
            },
        });
        await wrapper.get('[role="slider"]').trigger('keydown', { key: 'ArrowRight' });
        expect(wrapper.emitted('update:amount')?.[0]).toEqual(['12.35']);
        await wrapper.get('input').setValue('90');
        await wrapper.get('input').trigger('blur');
        expect(wrapper.emitted('update:amount')?.[1]).toEqual(['24.56']);
    });
    it('retains the existing percentage normalization', () => {
        const wrapper = mount(AppPercentageSplitEditor, {
            props: {
                users: [
                    { id: '1', name: 'Javi' },
                    { id: '2', name: 'Diva' },
                ],
                modelValue: {},
            },
            global: { stubs: { AppInput: input, Button: true } },
        });
        expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([{ '1': 50, '2': 50 }]);
    });
});
