import { flushPromises, shallowMount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
const personalMoney = ref<
    | {
          settings: {
              enabled: boolean;
              started_at: string;
              activated_at?: string | null;
              account_id?: number;
              transaction_cutoff_id?: number;
          };
          account_available: boolean;
          available_balance?: number | null;
      }
    | undefined
>();
vi.mock('@/modules/personal-money/composables/usePersonalMoney', () => ({
    usePersonalMoney: () => ({ state: personalMoney }),
}));
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

describe('private cash source choice', () => {
    it('omits the source on edit until the user explicitly chooses it', async () => {
        personalMoney.value = {
            settings: { enabled: true, started_at: '2026-01-01' },
            account_available: true,
        };
        const wrapper = render({
            initialValues: {
                id: '4',
                concept: 'Compra',
                amount: 20,
                accountId: '10',
                paymentSource: 'member_out_of_pocket',
                paidByUserId: '2',
                usesSharedAccountRules: true,
            },
        });
        expect(wrapper.find('#transaction-cash-source').exists()).toBe(true);
        expect(await submit(wrapper)).not.toHaveProperty('cashSource');
        wrapper
            .findAllComponents({ name: 'AppSearchSelect' })
            .find((item) => item.attributes('id') === 'transaction-cash-source')!
            .vm.$emit('update:modelValue', 'personal');
        await flushPromises();
        await submit(wrapper);
        expect(wrapper.emitted('submit')?.at(-1)?.[0]).toMatchObject({ cashSource: 'personal' });
        wrapper.unmount();
        personalMoney.value = undefined;
    });
    it('hides private source choices when another member pays', async () => {
        personalMoney.value = {
            settings: { enabled: true, started_at: '2026-01-01' },
            account_available: true,
        };
        const wrapper = render({
            initialValues: {
                concept: 'Compra',
                amount: 20,
                paymentSource: 'member_out_of_pocket',
                paidByUserId: '1',
            },
        });
        expect(wrapper.find('#transaction-cash-source').exists()).toBe(false);
        expect(await submit(wrapper)).not.toHaveProperty('cashSource');
        wrapper.unmount();
        personalMoney.value = undefined;
    });
    it('hides the private source selector when the available balance cannot be calculated', () => {
        personalMoney.value = {
            settings: { enabled: true, started_at: '2026-01-01' },
            account_available: true,
            available_balance: null,
        };
        const wrapper = render({
            initialValues: {
                concept: 'Compra',
                amount: 20,
                paymentSource: 'member_out_of_pocket',
                paidByUserId: '2',
            },
        });
        expect(wrapper.find('#transaction-cash-source').exists()).toBe(false);
        wrapper.unmount();
        personalMoney.value = undefined;
    });
    it('defaults a shared income contribution to personal money and allows changing its source', async () => {
        personalMoney.value = {
            settings: { enabled: true, started_at: '2026-01-01' },
            account_available: true,
            available_balance: 100,
        };
        const wrapper = render({
            initialValues: { type: 'income', concept: 'Aportación', amount: 20 },
        });
        expect(wrapper.find('#transaction-cash-source').exists()).toBe(true);
        const source = wrapper
            .findAllComponents({ name: 'AppSearchSelect' })
            .find((item) => item.attributes('id') === 'transaction-cash-source')!;
        expect(source.props('modelValue')).toBe('personal');
        expect(await submit(wrapper)).toMatchObject({ type: 'income', cashSource: 'personal' });
        wrapper
            .findAllComponents({ name: 'AppSearchSelect' })
            .find((item) => item.attributes('id') === 'transaction-cash-source')!
            .vm.$emit('update:modelValue', 'external');
        await flushPromises();
        await submit(wrapper);
        expect(wrapper.emitted('submit')?.at(-1)?.[0]).toMatchObject({
            type: 'income',
            cashSource: 'external',
        });
        wrapper.unmount();
        personalMoney.value = undefined;
    });
});

describe('activation-based private money', () => {
    function activate() {
        personalMoney.value = {
            settings: {
                enabled: true,
                started_at: '2026-10-11',
                activated_at: '2026-10-11T15:00:00Z',
                account_id: 10,
                transaction_cutoff_id: 5,
            },
            account_available: true,
            available_balance: 10000,
        };
    }
    it('defaults new current shared out of pocket expenses to personal money', async () => {
        activate();
        const wrapper = render({
            initialValues: {
                concept: 'Today',
                amount: 100,
                date: '2026-10-11',
                paymentSource: 'member_out_of_pocket',
                paidByUserId: '2',
            },
        });
        await flushPromises();
        expect(await submit(wrapper)).toMatchObject({ cashSource: 'personal' });
        wrapper.unmount();
        personalMoney.value = undefined;
    });
    it('requires a decision before submitting a historical personal movement', async () => {
        activate();
        const wrapper = render({
            accountUsers: members.slice(1),
            initialValues: {
                type: 'income',
                concept: 'Old salary',
                amount: 100,
                date: '2026-10-01',
            },
        });
        await flushPromises();
        const source = wrapper
            .findAllComponents({ name: 'AppSearchSelect' })
            .find((item) => item.attributes('id') === 'transaction-cash-source')!;
        expect(source.props('options').map((option: { label: string }) => option.label)).toEqual([
            'Ya está incluido en mi saldo inicial',
            'Aplicar a mi disponible',
        ]);
        expect(await submit(wrapper)).toBeUndefined();
        expect(source.props('error')).toContain('Elige si este movimiento');
        source.vm.$emit('update:modelValue', 'external');
        await flushPromises();
        expect(await submit(wrapper)).toMatchObject({ cashSource: 'external' });
        wrapper.unmount();
        personalMoney.value = undefined;
    });
    it('leaves historical shared expenses without a default decision', async () => {
        activate();
        const wrapper = render({
            initialValues: {
                concept: 'Old expense',
                amount: 100,
                date: '2026-10-01',
                paymentSource: 'member_out_of_pocket',
                paidByUserId: '2',
            },
        });
        await flushPromises();
        expect(await submit(wrapper)).toBeUndefined();
        const source = wrapper
            .findAllComponents({ name: 'AppSearchSelect' })
            .find((item) => item.attributes('id') === 'transaction-cash-source')!;
        source.vm.$emit('update:modelValue', 'personal');
        await flushPromises();
        expect(await submit(wrapper)).toMatchObject({ cashSource: 'personal' });
        wrapper.unmount();
        personalMoney.value = undefined;
    });
    it('does not offer a private link while editing a preactivation transaction', async () => {
        activate();
        const wrapper = render({
            initialValues: {
                id: '4',
                accountId: '10',
                usesSharedAccountRules: true,
                concept: 'Earlier expense',
                amount: 100,
                date: '2026-10-11',
                paymentSource: 'member_out_of_pocket',
                paidByUserId: '2',
            },
        });
        expect(wrapper.find('#transaction-cash-source').exists()).toBe(false);
        expect(await submit(wrapper)).not.toHaveProperty('cashSource');
        wrapper.unmount();
        personalMoney.value = undefined;
    });
    it('does not send an immediate cash choice for a future dated transaction', async () => {
        activate();
        const wrapper = render({
            initialValues: {
                concept: 'Future expense',
                amount: 100,
                date: '2099-10-11',
                paymentSource: 'member_out_of_pocket',
                paidByUserId: '2',
            },
        });
        await flushPromises();
        expect(wrapper.find('#transaction-cash-source').exists()).toBe(false);
        expect(await submit(wrapper)).not.toHaveProperty('cashSource');
        wrapper.unmount();
        personalMoney.value = undefined;
    });
});
