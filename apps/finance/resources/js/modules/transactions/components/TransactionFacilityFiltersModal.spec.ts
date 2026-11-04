import { mount } from '@vue/test-utils';
import { defineComponent } from 'vue';
import { describe, expect, it } from 'vitest';
import TransactionFacilityFiltersModal from './TransactionFacilityFiltersModal.vue';
import {
    currentMonthRange,
    validateTransactionPeriod,
} from '@/modules/transactions/lib/transactionPeriod';

const modalStub = defineComponent({
    props: ['open'],
    emits: ['close'],
    template:
        '<div v-if="open"><button aria-label="Cerrar" @click="$emit(\'close\')">Cerrar</button><slot/><slot name="footer"/></div>',
});
const dateStub = defineComponent({
    props: ['modelValue', 'id', 'error'],
    emits: ['update:modelValue'],
    template:
        '<div><input :id="id" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)"/><span v-if="error">{{error}}</span></div>',
});
function render() {
    return mount(TransactionFacilityFiltersModal, {
        props: { open: true, startDate: '2026-01-01', endDate: '2026-01-31' },
        global: {
            stubs: {
                AppModal: modalStub,
                TransactionAdditionalFilters: true,
                AppDatePicker: dateStub,
                AppButton: { template: '<button><slot/></button>' },
            },
        },
    });
}

describe('transaction period filter drafts', () => {
    it('applies a complete range only on submit and discards cancelled edits', async () => {
        const wrapper = render();
        await wrapper.get('#transaction-facility-start-date').setValue('2026-01-05');
        expect(wrapper.emitted('apply')).toBeUndefined();
        await wrapper.get('button[aria-label="Cerrar"]').trigger('click');
        await wrapper.setProps({ open: false });
        await wrapper.setProps({ open: true });
        expect(
            (wrapper.get('#transaction-facility-start-date').element as HTMLInputElement).value,
        ).toBe('2026-01-01');
        await wrapper.get('#transaction-facility-start-date').setValue('2026-01-05');
        await wrapper.get('form').trigger('submit');
        expect(wrapper.emitted('apply')).toEqual([
            [
                {
                    startDate: '2026-01-05',
                    endDate: '2026-01-31',
                    categories: [],
                    hasAttachments: null,
                },
            ],
        ]);
    });

    it('rejects reversed ranges and impossible dates even when the form is submitted', async () => {
        const wrapper = render();
        await wrapper.get('#transaction-facility-start-date').setValue('2026-02-01');
        await wrapper.get('form').trigger('submit');
        expect(wrapper.emitted('apply')).toBeUndefined();
        expect(wrapper.text()).toContain('La fecha inicial debe ser anterior a la final.');
        await wrapper.get('#transaction-facility-start-date').setValue('2026-02-30');
        await wrapper.get('form').trigger('submit');
        expect(wrapper.emitted('apply')).toBeUndefined();
        expect(validateTransactionPeriod('2024-02-29', '2024-03-01')).toBeNull();
    });

    it('resets only the draft to the current month until applying', async () => {
        const wrapper = render();
        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Limpiar')!
            .trigger('click');
        expect(wrapper.emitted('apply')).toBeUndefined();
        expect(wrapper.props('startDate')).toBe('2026-01-01');
        await wrapper.get('form').trigger('submit');
        expect(wrapper.emitted('apply')).toEqual([
            [{ ...currentMonthRange(), categories: [], hasAttachments: null }],
        ]);
    });
});

it('keeps category and receipt changes in the draft until applying and resets them on reopen', async () => {
    const wrapper = render();
    await wrapper.setProps({ selectedCategories: ['2'], hasAttachments: false, open: false });
    await wrapper.setProps({ open: true });
    const additional = wrapper.getComponent({ name: 'TransactionAdditionalFilters' });
    additional.vm.$emit('update:categories', ['3', 'uncategorized']);
    additional.vm.$emit('update:hasAttachments', true);
    await wrapper.vm.$nextTick();
    expect(wrapper.emitted('apply')).toBeUndefined();
    await wrapper.setProps({ open: false });
    await wrapper.setProps({ open: true });
    expect(
        wrapper.getComponent({ name: 'TransactionAdditionalFilters' }).props('categories'),
    ).toEqual(['2']);
    expect(
        wrapper.getComponent({ name: 'TransactionAdditionalFilters' }).props('hasAttachments'),
    ).toBe(false);
    await wrapper.get('form').trigger('submit');
    expect(wrapper.emitted('apply')?.[0]?.[0]).toMatchObject({
        categories: ['2'],
        hasAttachments: false,
    });
    wrapper.unmount();
});

it('blocks applying more than 100 categories with a visible explanation', async () => {
    const wrapper = render();
    wrapper.getComponent({ name: 'TransactionAdditionalFilters' }).vm.$emit(
        'update:categories',
        Array.from({ length: 101 }, (_, index) => String(index + 1)),
    );
    await wrapper.vm.$nextTick();
    await wrapper.get('form').trigger('submit');
    expect(wrapper.emitted('apply')).toBeUndefined();
    expect(wrapper.text()).toContain('Selecciona como máximo 100 categorías');
    wrapper.unmount();
});
