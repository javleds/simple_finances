import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

import type {
  AccountInvite,
  AccountInviteStatus,
} from '@/modules/accounts/schemas/accountInviteSchemas';
import FacilityInvitationsList from './FacilityInvitationsList.vue';

function invitation(status: AccountInviteStatus): AccountInvite {
  return {
    id: status,
    accountId: '1',
    userId: '2',
    email: 'member@example.com',
    percentage: 50,
    status,
    invitedAt: null,
    accountName: `Account ${status}`,
    invitedByName: 'Owner',
  };
}

describe('FacilityInvitationsList', () => {
  it('shows received invitation history and offers responses only for pending invitations', () => {
    const wrapper = mount(FacilityInvitationsList, {
      props: {
        invitations: ['pending', 'accepted', 'declined'].map((status) =>
          invitation(status as AccountInviteStatus),
        ),
      },
      global: {
        stubs: {
          AppCard: { template: '<div><slot /></div>' },
          AppText: { template: '<p><slot /></p>' },
          AppActionMenu: {
            template: "<button @click=\"$emit('action', 'accept')\">Respond</button>",
          },
        },
      },
    });

    expect(wrapper.text()).toContain('Pendiente');
    expect(wrapper.text()).toContain('Aceptada');
    expect(wrapper.text()).toContain('Rechazada');
    expect(wrapper.findAll('button')).toHaveLength(1);
  });
});
