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
        '<input class="split" :value="monetary.amount" :max="monetary.maximum" @input="$emit(\'update:amount\', $event.target.value)" />',
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
    balance: '100.00',
    totalSpent: '0.00',
    availableCredit: null,
    creditLine: null,
    closingDay: null,
    fundingAccountId: null,
    users: ['Javi', 'Diva'].map((name, i) => ({
        id: String(i + 1),
        name,
        email: '',
        allocationPercentage: 50,
        pendingExpenses: '0.00',
        custodyAmount: '0.00',
        settlementAmount: '0.00',
    })),
    virtualCustodians: [
        { id: '3', name: 'Mercadopago', custodyAmount: '100.00', settlementAmount: '0.00' },
    ],
    custodyByUser: [{ userId: '3', userName: 'Mercadopago', amount: '100.00' }],
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
            { userId: '1', amount: '23.01' },
            { userId: '2', amount: '76.99' },
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
            openingBalance: '15.25',
            transfers: [{ userId: '3', amount: '40.01' }],
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
                        amount: '25.01',
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
            openingBalance: '0.00',
            transfers: [{ userId: '3', amount: '74.99' }],
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
                        amount: '10.00',
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

it('renames with the current name and no financial inputs', async () => {
    const wrapper = render(true);
    await wrapper.setProps({ renaming: true });
    expect((wrapper.get('#custodian-name input').element as HTMLInputElement).value).toBe(
        'Mercadopago',
    );
    expect(wrapper.find('.split').exists()).toBe(false);
    expect(wrapper.find('#custodian-opening-balance').exists()).toBe(false);
    await wrapper.get('#custodian-name input').setValue('  Mercado Pago  ');
    await wrapper.get('[data-submit]').trigger('click');
    await flushPromises();
    expect(wrapper.emitted('save')).toEqual([['Mercado Pago']]);
});

it('validates renamed names and blocks saving while in flight', async () => {
    const wrapper = render(true);
    await wrapper.setProps({ renaming: true });
    await wrapper.get('#custodian-name input').setValue('   ');
    await wrapper.get('[data-submit]').trigger('click');
    await flushPromises();
    expect(wrapper.emitted('save')).toBeUndefined();
    await wrapper.get('#custodian-name input').setValue('Nu');
    await wrapper.setProps({ isSaving: true, error: 'No fue posible guardar.' });
    await wrapper.get('form').trigger('submit');
    await flushPromises();
    expect(wrapper.emitted('save')).toBeUndefined();
    expect(wrapper.text()).toContain('No fue posible guardar.');
});

it('cancels renaming without saving and resets the name on reopening', async () => {
    const wrapper = render(true);
    await wrapper.setProps({ renaming: true });
    await wrapper.get('#custodian-name input').setValue('Draft');
    wrapper.findComponent(modal).vm.$emit('action', 'close');
    expect(wrapper.emitted('close')).toHaveLength(1);
    expect(wrapper.emitted('save')).toBeUndefined();
    await wrapper.setProps({ open: false });
    await wrapper.setProps({ open: true });
    expect((wrapper.get('#custodian-name input').element as HTMLInputElement).value).toBe(
        'Mercadopago',
    );
});
