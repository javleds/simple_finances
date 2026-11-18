import { flushPromises, mount } from '@vue/test-utils';
import { defineComponent } from 'vue';
import { describe, expect, it } from 'vitest';
import AccountCustodianModal from './AccountCustodianModal.vue';
import type { Account } from '../types';

const modal = defineComponent({
    props: ['actions'],
    emits: ['action'],
    template:
        "<div><slot /><button data-submit @click=\"$emit('action', 'save')\">Guardar</button></div>",
});
const input = defineComponent({
    props: ['modelValue', 'error'],
    emits: ['update:modelValue'],
    template:
        '<div><input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /><span>{{ error }}</span></div>',
});
const split = defineComponent({
    props: ['monetary'],
    emits: ['update:amount'],
    template:
        '<input class="split" :value="monetary.amount" :max="monetary.maximum" @input="$emit(\'update:amount\', Number($event.target.value))" />',
});
const account: Account = {
    id: '1',
    ownerId: '1',
    name: 'Kali',
    description: '',
    color: null,
    isVirtual: false,
    isCredit: false,
    status: 'Activo',
    balance: 100,
    totalSpent: 0,
    availableCredit: null,
    creditLine: null,
    closingDay: null,
    fundingAccountId: null,
    users: ['Javi', 'Diva'].map((name, i) => ({
        id: String(i + 1),
        name,
        email: '',
        allocationPercentage: 50,
        pendingExpenses: 0,
        custodyAmount: 0,
        settlementAmount: 0,
    })),
    virtualCustodians: [{ id: '3', name: 'Mercadopago', custodyAmount: 100, settlementAmount: 0 }],
    custodyByUser: [{ userId: '3', userName: 'Mercadopago', amount: 100 }],
    settlementsByUser: [],
    pendingReimbursements: [],
};
function render(removing = false) {
    return mount(AccountCustodianModal, {
        props: {
            open: true,
            account,
            custodian: removing ? account.virtualCustodians![0]! : null,
            isSaving: false,
            error: null,
        },
        global: {
            stubs: {
                AppModal: modal,
                AppInput: input,
                AppPercentageSplitEditor: split,
                Message: { template: '<div><slot /></div>' },
            },
        },
    });
}

describe('virtual custodian form', () => {
    it('requires the entire custody and caps distributions at the remaining amount', async () => {
        const wrapper = render(true);
        await wrapper.get('[data-submit]').trigger('click');
        await flushPromises();
        expect(wrapper.emitted('save')).toBeUndefined();
        const inputs = wrapper.findAll('.split');
        await inputs[0]!.setValue('23.01');
        expect(inputs[1]!.attributes('max')).toBe('76.99');
        await inputs[1]!.setValue('100');
        await wrapper.get('[data-submit]').trigger('click');
        await flushPromises();
        expect(wrapper.emitted('save')?.[0]?.[0]).toEqual([
            { userId: '1', amount: 23.01 },
            { userId: '2', amount: 76.99 },
        ]);
        expect(wrapper.text()).toContain('Por repartir: $0.00');
    });

    it('keeps additional balance separate from transferred custody', async () => {
        const wrapper = render();
        await wrapper.get('#custodian-name input').setValue('Nu');
        await wrapper.get('#custodian-opening-balance input').setValue('15.25');
        await wrapper.get('.split').setValue('40.01');
        await wrapper.get('[data-submit]').trigger('click');
        await flushPromises();
        expect(wrapper.emitted('save')?.[0]?.[0]).toEqual({
            name: 'Nu',
            openingBalance: 15.25,
            transfers: [{ userId: '3', amount: 40.01 }],
        });
        expect(wrapper.text()).toContain('$115.25');
    });

    it('preserves custody reserved for pending reimbursements when creating a custodian', async () => {
        const wrapper = render();
        await wrapper.setProps({
            account: {
                ...account,
                pendingReimbursements: [
                    {
                        fromUserId: '3',
                        fromUserName: 'Mercadopago',
                        toUserId: '1',
                        toUserName: 'Javi',
                        amount: 25.01,
                        actionType: 'custody_to_user',
                        items: [],
                    },
                ],
            },
        });
        await wrapper.get('#custodian-name input').setValue('Nu');
        expect(wrapper.get('.split').attributes('max')).toBe('74.99');
        await wrapper.get('.split').setValue('100');
        await wrapper.get('[data-submit]').trigger('click');
        await flushPromises();
        expect(wrapper.emitted('save')?.[0]?.[0]).toEqual({
            name: 'Nu',
            openingBalance: 0,
            transfers: [{ userId: '3', amount: 74.99 }],
        });
    });

    it('blocks pending obligations even with a zero net settlement', async () => {
        const wrapper = render(true);
        await wrapper.setProps({
            account: {
                ...account,
                pendingReimbursements: [
                    {
                        fromUserId: '3',
                        fromUserName: 'Mercadopago',
                        toUserId: '1',
                        toUserName: 'Javi',
                        amount: 10,
                        actionType: 'custody_to_user',
                        items: [],
                    },
                ],
            },
        });
        await wrapper.findAll('.split')[0]!.setValue('100');
        await wrapper.get('[data-submit]').trigger('click');
        await flushPromises();
        expect(wrapper.emitted('save')).toBeUndefined();
        expect(wrapper.text()).toContain('Resuelve los reembolsos pendientes');
    });

    it('blocks repeated submissions during saving and clears distributions when reopened', async () => {
        const wrapper = render(true);
        await wrapper.findAll('.split')[0]!.setValue('100');
        await wrapper.setProps({ isSaving: true });
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(wrapper.emitted('save')).toBeUndefined();
        await wrapper.setProps({ open: false, isSaving: false });
        await wrapper.setProps({ open: true });
        expect(wrapper.text()).toContain('Por repartir: $100.00');
    });
});
