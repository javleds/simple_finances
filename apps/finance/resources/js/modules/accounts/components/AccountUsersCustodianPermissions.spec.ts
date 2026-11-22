import { mount, shallowMount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import PrimeVue from 'primevue/config';
import AccountUsersToolbar from './AccountUsersToolbar.vue';
import AccountUsersList from './AccountUsersList.vue';
import AccountUserListItem from './AccountUserListItem.vue';

describe('account custodian permissions', () => {
    it('only offers custodian creation in the toolbar', async () => {
        const wrapper = mount(AccountUsersToolbar, {
            props: { searchTerm: '', canCreateCustodian: true },
            global: { plugins: [PrimeVue] },
        });
        expect(wrapper.findAll('button')).toHaveLength(1);
        expect(wrapper.get('button').text()).toContain('Custodio virtual');
        await wrapper.get('button').trigger('click');
        expect(wrapper.emitted('createCustodian')).toHaveLength(1);
        await wrapper.setProps({ canCreateCustodian: false });
        expect(wrapper.findAll('button')).toHaveLength(0);
    });

    it('lets members manage custodians without managing real users', () => {
        const wrapper = shallowMount(AccountUsersList, {
            props: {
                canManage: false,
                canManageCustodians: true,
                users: [
                    {
                        id: '1',
                        name: 'Member',
                        email: '',
                        allocationPercentage: 100,
                        pendingExpenses: '0.00',
                        custodyAmount: '0.00',
                        settlementAmount: '0.00',
                    },
                ],
                custodians: [
                    { id: '2', name: 'Nu', custodyAmount: '100.00', settlementAmount: '0.00' },
                ],
            },
        });
        const rows = wrapper.findAllComponents(AccountUserListItem);
        expect(rows[0]!.props('canManage')).toBe(false);
        expect(rows[1]!.props('canManage')).toBe(true);
        rows[1]!.vm.$emit('delete', '2');
        expect(wrapper.emitted('deleteCustodian')).toEqual([['2']]);
        rows[1]!.vm.$emit('edit', '2');
        expect(wrapper.emitted('renameCustodian')).toEqual([['2']]);
    });
});
