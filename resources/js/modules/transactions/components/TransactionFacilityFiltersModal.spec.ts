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
            [{ startDate: '2026-01-05', endDate: '2026-01-31' }],
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
            .find((button) => button.text() === 'Mes actual')!
            .trigger('click');
        expect(wrapper.emitted('apply')).toBeUndefined();
        expect(wrapper.props('startDate')).toBe('2026-01-01');
        await wrapper.get('form').trigger('submit');
        expect(wrapper.emitted('apply')).toEqual([[currentMonthRange()]]);
    });
});
