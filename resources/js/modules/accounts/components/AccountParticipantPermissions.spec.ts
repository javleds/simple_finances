import { shallowMount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

import AccountInvitationListItem from './AccountInvitationListItem.vue';
import AccountUserListItem from './AccountUserListItem.vue';

describe('account participant permissions', () => {
  it.each([true, false])('shows member administration only when canManage is %s', (canManage) => {
    const wrapper = shallowMount(AccountUserListItem, {
      props: {
        canManage,
        itemId: '2',
        name: 'Member',
        email: 'member@example.com',
        roleLabel: 'Miembro',
        accessLabel: 'Cuenta compartida',
        allocationPercentage: 35,
        custodyAmount: 0,
        settlementAmount: 0,
        status: 'active',
      },
      global: { renderStubDefaultSlot: true },
    });

    expect(wrapper.text()).toContain('Member');
    expect(wrapper.findComponent({ name: 'AppActionMenu' }).exists()).toBe(canManage);
  });

  it.each([true, false])('shows invitation administration only when canManage is %s', (canManage) => {
    const wrapper = shallowMount(AccountInvitationListItem, {
      props: {
        canManage,
        itemId: '1',
        email: 'member@example.com',
        percentageLabel: '35%',
        metaLabel: 'Hoy',
        status: 'pending',
      },
      global: { renderStubDefaultSlot: true },
    });

    expect(wrapper.text()).toContain('member@example.com');
    expect(wrapper.findComponent({ name: 'AppActionMenu' }).exists()).toBe(canManage);
  });
});
