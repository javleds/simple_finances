import { flushPromises, shallowMount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import TransactionsForm from './TransactionsForm.vue';

const members = [
    {
        id: '1',
        name: 'Ana',
        email: 'ana@example.com',
        pendingExpenses: 0,
        custodyAmount: 0,
        settlementAmount: 0,
        allocationPercentage: 50,
    },
    {
        id: '2',
        name: 'Luis',
        email: 'luis@example.com',
        pendingExpenses: 0,
        custodyAmount: 0,
        settlementAmount: 0,
        allocationPercentage: 50,
    },
];

function render(props: Partial<InstanceType<typeof TransactionsForm>['$props']> = {}) {
    return shallowMount(TransactionsForm, {
        global: { renderStubDefaultSlot: true },
        props: {
            lockedAccountId: '10',
            accountUsers: members,
            currentUserId: '2',
            accountBalance: 100,
            initialValues: { concept: 'Compra', amount: 20, date: '2026-10-10' },
            ...props,
        },
    });
}

async function submit(wrapper: ReturnType<typeof render>) {
    await wrapper.get('form').trigger('submit');
    await flushPromises();
    return wrapper.emitted('submit')?.[0]?.[0];
}

describe('transaction account context', () => {
    it('hides member controls and submits the sole member for individual expenses', async () => {
        const wrapper = render({
            accountUsers: members.slice(0, 1),
            initialValues: {
                concept: 'Compra',
                amount: 20,
                paidByUserId: '2',
                paymentSource: 'member_out_of_pocket',
                userPayments: { '1': 50, '2': 50 },
            },
        });
        expect(wrapper.find('#transaction-paid-by').exists()).toBe(false);
        expect(wrapper.find('#transaction-payment-source').exists()).toBe(false);
        expect(await submit(wrapper)).toMatchObject({
            paidByUserId: '1',
            paymentSource: 'account_fund',
            splitBetweenUsers: false,
            userPayments: {},
        });
    });

    it('hides custody and submits the sole member for individual income', async () => {
        const wrapper = render({
            accountUsers: members.slice(0, 1),
            initialValues: { type: 'income', concept: 'Ingreso', amount: 20 },
        });
        expect(wrapper.find('#transaction-custodian').exists()).toBe(false);
        expect(wrapper.find('#transaction-financial-goal').exists()).toBe(true);
        expect(await submit(wrapper)).toMatchObject({ custodianUserId: '1', paymentSource: null });
    });

    it('shows shared payment choices and defaults new payments to the current member', async () => {
        const wrapper = render({ initialValues: null });
        expect(wrapper.find('#transaction-paid-by').exists()).toBe(true);
        expect(wrapper.find('#transaction-payment-source').exists()).toBe(true);
        expect(wrapper.getComponent({ name: 'AppSearchSelect' }).props('modelValue')).toBe('2');
    });

    it.each([0, -50])(
        'uses personal money for new shared expenses with balance %s',
        async (balance) => {
            const wrapper = render({ accountBalance: balance });
            expect(wrapper.text()).toContain('La cuenta no tiene saldo disponible');
            expect(await submit(wrapper)).toMatchObject({ paymentSource: 'member_out_of_pocket' });
        },
    );

    it('preserves the payment source when editing an existing shared expense', async () => {
        const wrapper = render({
            accountBalance: 0,
            initialValues: {
                id: '5',
                concept: 'Compra',
                amount: 20,
                paymentSource: 'account_fund',
            },
        });
        expect(await submit(wrapper)).toMatchObject({ paymentSource: 'account_fund' });
    });
});
