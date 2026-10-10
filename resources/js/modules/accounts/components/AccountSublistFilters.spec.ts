import { mount } from '@vue/test-utils';
import { defineComponent } from 'vue';
import { describe, expect, it } from 'vitest';

import AccountGoalFiltersModal from './AccountGoalFiltersModal.vue';
import AccountInvitationFiltersModal from './AccountInvitationFiltersModal.vue';
import AccountTransactionFiltersModal from './AccountTransactionFiltersModal.vue';

const modalStub = defineComponent({
    props: ['open'],
    emits: ['close'],
    template: `<div v-if="open"><button data-action="close" @click="$emit('close')">Cerrar</button><slot /><slot name="footer" /></div>`,
});
const buttonStub = defineComponent({
    template: '<button><slot /></button>',
});

const cases = [
    {
        name: 'goals',
        component: AccountGoalFiltersModal,
        selected: 'selectedStatuses',
        options: 'options',
        values: ['on-track', 'completed'],
    },
    {
        name: 'invitations',
        component: AccountInvitationFiltersModal,
        selected: 'selectedStatuses',
        options: 'options',
        values: ['pending', 'accepted'],
    },
    {
        name: 'transactions',
        component: AccountTransactionFiltersModal,
        selected: 'selectedTypes',
        options: 'typeOptions',
        values: ['income', 'expense'],
    },
] as const;

describe.each(cases)('$name filter drafts', ({ component, selected, options, values }) => {
    function render() {
        return mount(component as typeof AccountGoalFiltersModal, {
            props: {
                open: true,
                [selected]: [values[0]],
                [options]: values.map((value) => ({ value, label: value })),
            } as never,
            global: { stubs: { AppModal: modalStub, AppButton: buttonStub } },
        });
    }

    it('applies a draft once and closes', async () => {
        const wrapper = render();
        await wrapper.get(`input[value="${values[1]}"]`).setValue(true);
        expect(wrapper.emitted('apply')).toBeUndefined();
        await wrapper.get('form').trigger('submit');
        expect(wrapper.emitted('apply')).toEqual([[[values[0], values[1]]]]);
        expect(wrapper.emitted('close')).toHaveLength(1);
    });

    it('clears the draft without changing applied values and discards it on reopen', async () => {
        const wrapper = render();
        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Limpiar filtros')!
            .trigger('click');
        expect(wrapper.emitted('apply')).toBeUndefined();
        await wrapper.get('form').trigger('submit');
        expect(wrapper.emitted('apply')).toEqual([[[]]]);
        await wrapper.setProps({ open: false });
        await wrapper.setProps({ open: true });
        await wrapper.get(`input[value="${values[1]}"]`).setValue(true);
        await wrapper.get('[data-action="close"]').trigger('click');
        await wrapper.setProps({ open: false });
        await wrapper.setProps({ open: true });
        await wrapper.get('form').trigger('submit');
        expect(wrapper.emitted('apply')?.[1]).toEqual([[values[0]]]);
    });
});
