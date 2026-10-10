import { ref } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import type { AccountInvite } from '@/modules/accounts/schemas/accountInviteSchemas';
import { useFacilityInvitationActions } from './useFacilityInvitationActions';

const { respond } = vi.hoisted(() => ({ respond: vi.fn() }));

vi.mock('@/modules/accounts/repositories/accountInvitesRepository', () => ({
  createAccountInvitesRepository: () => ({ respond }),
}));
vi.mock('@tanstack/vue-query', () => ({
  useMutation: (options: { mutationFn: (payload: unknown) => Promise<unknown> }) => ({
    mutateAsync: options.mutationFn,
    isPending: ref(false),
  }),
}));

const pendingInvite: AccountInvite = {
  id: '1',
  accountId: '2',
  userId: '3',
  email: 'member@example.com',
  percentage: 50,
  status: 'pending',
  invitedAt: null,
  accountName: 'Home',
  invitedByName: 'Owner',
};

describe('useFacilityInvitationActions', () => {
  beforeEach(() => vi.clearAllMocks());

  it('keeps the invitation in the history after responding', async () => {
    const invitations = ref([pendingInvite]);
    const replaceInvitation = vi.fn(
      (matcher: (invite: AccountInvite) => boolean, updated: AccountInvite) => {
        invitations.value = invitations.value.map((invite) => (matcher(invite) ? updated : invite));
      },
    );
    respond.mockResolvedValue({ ...pendingInvite, status: 'accepted' });
    const actions = useFacilityInvitationActions({ invitations, replaceInvitation });

    actions.openInvitationAction('1', 'accepted');
    await actions.confirmInvitationAction();

    expect(invitations.value).toHaveLength(1);
    expect(invitations.value[0]?.status).toBe('accepted');
    expect(actions.selectedInvitation.value).toBeNull();
  });

  it('does not respond to an already resolved invitation', async () => {
    const actions = useFacilityInvitationActions({
      invitations: ref([{ ...pendingInvite, status: 'accepted' }]),
      replaceInvitation: vi.fn(),
    });

    actions.openInvitationAction('1', 'declined');
    await actions.confirmInvitationAction();

    expect(respond).not.toHaveBeenCalled();
  });
});
