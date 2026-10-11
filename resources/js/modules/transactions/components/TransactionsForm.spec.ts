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
        global: { renderStubDefaultSlot: true, stubs: { AppPercentageSplitEditor: false } },
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

it('keeps pending files and their saved transaction when the selected row is replaced', async () => {
    const wrapper = render({
        initialValues: { id: '123', concept: 'Compra', amount: 20, date: '2026-10-10' },
    });
    const receipts = wrapper.getComponent({ name: 'TransactionAttachments' });
    const draft = receipts.props('draft');
    draft.files.push({
        file: new File(['receipt'], 'receipt.pdf'),
        uploadId: 'receipt',
        uploaded: false,
    });
    draft.transactionId = '123';
    await wrapper.setProps({
        initialValues: { id: '123', concept: 'Compra editada', amount: 30, date: '2026-10-10' },
    });
    expect(receipts.props('draft')).toBe(draft);
    expect(draft.transactionId).toBe('123');
    expect(draft.files).toHaveLength(1);
    await wrapper.setProps({
        initialValues: { id: '456', concept: 'Otro', amount: 10, date: '2026-10-10' },
    });
    expect(draft.files).toHaveLength(0);
    expect(draft.transactionId).toBeNull();
    wrapper.unmount();
});

describe('saved transaction financial rules', () => {
    it('uses personal controls for a new sole-member expense without sending a snapshot', async () => {
        const wrapper = render({
            accountUsers: members.slice(0, 1),
            initialValues: { concept: 'Compra', amount: 20, usesSharedAccountRules: false },
        });
        const payload = await submit(wrapper);
        expect(payload).toMatchObject({
            paidByUserId: '1',
            paymentSource: 'account_fund',
            splitBetweenUsers: false,
            userPayments: {},
        });
        expect(payload).not.toHaveProperty('usesSharedAccountRules');
        expect(payload).not.toHaveProperty('uses_shared_account_rules');
        wrapper.unmount();
    });
    it.each([true, null])(
        'preserves saved financial choices with one current member and snapshot %s',
        async (snapshot) => {
            const wrapper = render({
                accountUsers: members.slice(0, 1),
                initialValues: {
                    id: '8',
                    accountId: '10',
                    concept: 'Compra compartida',
                    amount: 20,
                    usesSharedAccountRules: snapshot,
                    paymentSource: 'member_out_of_pocket',
                    paidByUserId: '2',
                    userPayments: { '1': 50, '2': 50 },
                },
            });
            expect(wrapper.find('#transaction-payment-source').exists()).toBe(snapshot === true);
            expect(await submit(wrapper)).toMatchObject({
                paidByUserId: '2',
                paymentSource: 'member_out_of_pocket',
                splitBetweenUsers: true,
                userPayments: { '1': 50, '2': 50 },
            });
            wrapper.unmount();
        },
    );
    it('keeps a personal snapshot simplified when the account now has multiple members', async () => {
        const wrapper = render({
            initialValues: {
                id: '8',
                accountId: '10',
                concept: 'Compra personal',
                amount: 20,
                usesSharedAccountRules: false,
                paymentSource: 'account_fund',
                paidByUserId: '1',
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
        wrapper.unmount();
    });
});

it('preserves the historical custodian when a shared income account has one remaining member', async () => {
    const wrapper = render({
        accountUsers: members.slice(0, 1),
        initialValues: {
            id: '8',
            accountId: '10',
            type: 'income',
            concept: 'Depósito',
            amount: 20,
            usesSharedAccountRules: true,
            custodianUserId: '2',
            custodianUserName: 'Luis',
        },
    });
    const custodian = wrapper.getComponent({ name: 'AppSearchSelect' });
    expect(custodian.props('options')).toContainEqual({
        value: '2',
        label: 'Luis',
        description: 'Participó en este movimiento',
    });
    expect(await submit(wrapper)).toMatchObject({ custodianUserId: '2' });
    expect(wrapper.text()).toContain('conserva las reglas');
    wrapper.unmount();
});

it('uses destination members instead of the source snapshot when the locked account changes', async () => {
    const wrapper = render({
        lockedAccountId: '10',
        initialValues: {
            id: '8',
            accountId: '10',
            concept: 'Compra',
            amount: 20,
            usesSharedAccountRules: false,
            paidByUserId: '1',
        },
    });
    expect(wrapper.find('#transaction-payment-source').exists()).toBe(false);
    await wrapper.setProps({ lockedAccountId: '20' });
    expect(wrapper.find('#transaction-payment-source').exists()).toBe(true);
    expect(await submit(wrapper)).toMatchObject({ accountId: '20' });
    wrapper.unmount();
});

it('keeps historical allocations through the real split editor and excludes unrelated historical participants', async () => {
    const wrapper = render({
        accountUsers: members.slice(0, 1),
        initialValues: {
            id: '8',
            accountId: '10',
            concept: 'Compra',
            amount: 20,
            usesSharedAccountRules: true,
            paidByUserId: '2',
            paidByUserName: 'Luis',
            custodianUserId: '4',
            custodianUserName: 'Otra persona',
            creatorId: '3',
            creatorName: 'Autor anterior',
            paymentSource: 'member_out_of_pocket',
            userPayments: { '1': 40, '2': 60 },
        },
    });
    await flushPromises();
    const editor = wrapper.getComponent({ name: 'AppPercentageSplitEditor' });
    expect(editor.props('users')).toEqual([
        { id: '1', name: 'Ana' },
        { id: '2', name: 'Luis' },
    ]);
    const payer = wrapper.getComponent({ name: 'AppSearchSelect' });
    expect(payer.props('options').map((option: { value: string }) => option.value)).toEqual([
        '1',
        '2',
    ]);
    expect(await submit(wrapper)).toMatchObject({
        paidByUserId: '2',
        paymentSource: 'member_out_of_pocket',
        splitBetweenUsers: true,
        userPayments: { '1': 40, '2': 60 },
    });
    wrapper.unmount();
});
