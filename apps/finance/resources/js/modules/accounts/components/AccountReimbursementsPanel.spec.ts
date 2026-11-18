import { mount } from '@vue/test-utils';
import { defineComponent } from 'vue';
import { describe, expect, it } from 'vitest';
import AccountReimbursementsPanel from './AccountReimbursementsPanel.vue';
import type { Account, AccountPendingReimbursement } from '../types';

const reimbursement: AccountPendingReimbursement = {
    fromUserId: '8',
    fromUserName: 'Mercadopago',
    toUserId: '9',
    toUserName: 'Nu',
    amount: 10,
    actionType: 'custody_to_user',
    items: [],
};
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
    users: [
        {
            id: '1',
            name: 'Javi',
            email: '',
            allocationPercentage: 100,
            pendingExpenses: 0,
            custodyAmount: 0,
            settlementAmount: 0,
        },
    ],
    virtualCustodians: [
        { id: '8', name: 'Mercadopago', custodyAmount: 100, settlementAmount: -10 },
        { id: '9', name: 'Nu', custodyAmount: 0, settlementAmount: 10 },
    ],
    custodyByUser: [],
    settlementsByUser: [],
    pendingReimbursements: [reimbursement],
};
const button = defineComponent({
    props: ['ariaLabel'],
    template: '<button :aria-label="ariaLabel"><slot /></button>',
});
function render(currentUserId: string, pending = reimbursement) {
    return mount(AccountReimbursementsPanel, {
        props: { accounts: [{ ...account, pendingReimbursements: [pending] }], currentUserId },
        global: { stubs: { AppIconButton: button, AppButton: button, AppModal: true } },
    });
}

describe('virtual custodian reimbursements', () => {
    it('allows a real account member to record a transfer between virtual custodians', async () => {
        const wrapper = render('1');
        await wrapper.get('[aria-label="Registrar pago entre custodios"]').trigger('click');
        expect(wrapper.emitted('settle')?.[0]).toEqual(['1', reimbursement]);
    });
    it('does not expose a virtual transfer to an unrelated actor', () => {
        expect(render('20').find('[aria-label="Registrar pago entre custodios"]').exists()).toBe(
            false,
        );
    });
    it('preserves existing real-member settlement permissions', () => {
        const wrapper = render('1', { ...reimbursement, fromUserId: '2', toUserId: '3' });
        expect(wrapper.findAll('button')).toHaveLength(1);
    });
    it('prevents repeat mutations while a transfer is pending', async () => {
        const wrapper = render('1');
        await wrapper.setProps({ isTransferring: true });
        await wrapper.get('[aria-label="Registrar pago entre custodios"]').trigger('click');
        expect(wrapper.emitted('settle')).toBeUndefined();
    });
});
