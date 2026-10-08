import { useMutation } from '@tanstack/vue-query';
import { computed, ref, type Ref } from 'vue';

import { createAccountInvitesRepository } from '@/modules/accounts/repositories/accountInvitesRepository';
import type { AccountInvite } from '@/modules/accounts/schemas/accountInviteSchemas';
import { resolveApiErrorMessage } from '@/modules/shared/lib/apiErrors';

export type PendingInvitationAction = 'accepted' | 'declined';

type UseFacilityInvitationActionsOptions = {
  invitations: Ref<AccountInvite[]>;
  removeInvitation: (matcher: (invitation: AccountInvite) => boolean) => void;
  replaceInvitation: (
    matcher: (invitation: AccountInvite) => boolean,
    invitation: AccountInvite,
  ) => void;
};

const accountInvitesRepository = createAccountInvitesRepository();

export function useFacilityInvitationActions(options: UseFacilityInvitationActionsOptions) {
  const saveError = ref<string | null>(null);
  const selectedInvitationId = ref<string | null>(null);
  const pendingAction = ref<PendingInvitationAction | null>(null);

  const respondMutation = useMutation({
    mutationFn: ({
      invitation,
      status,
    }: {
      invitation: AccountInvite;
      status: PendingInvitationAction;
    }) =>
      accountInvitesRepository.respond(invitation.id, {
        accountId: invitation.accountId,
        email: invitation.email,
        percentage: invitation.percentage,
        status,
      }),
  });

  const selectedInvitation = computed(() => {
    if (!selectedInvitationId.value) {
      return null;
    }

    return (
      options.invitations.value.find(
        (invitation) => invitation.id === selectedInvitationId.value,
      ) ?? null
    );
  });

  const isSaving = computed(() => respondMutation.isPending.value);

  function openInvitationAction(inviteId: string, action: PendingInvitationAction): void {
    saveError.value = null;
    selectedInvitationId.value = inviteId;
    pendingAction.value = action;
  }

  function closeInvitationAction(): void {
    selectedInvitationId.value = null;
    pendingAction.value = null;
    saveError.value = null;
  }

  async function confirmInvitationAction(): Promise<void> {
    if (!selectedInvitation.value || !pendingAction.value) {
      return;
    }

    saveError.value = null;

    try {
      const updatedInvitation = await respondMutation.mutateAsync({
        invitation: selectedInvitation.value,
        status: pendingAction.value,
      });

      if (updatedInvitation.status !== 'pending') {
        options.removeInvitation((invitation) => invitation.id === updatedInvitation.id);
      } else {
        options.replaceInvitation(
          (invitation) => invitation.id === updatedInvitation.id,
          updatedInvitation,
        );
      }

      closeInvitationAction();
    } catch (error) {
      saveError.value = resolveApiErrorMessage(error, 'No fue posible responder la invitación.');
    }
  }

  return {
    selectedInvitation,
    pendingAction,
    isSaving,
    saveError,
    openInvitationAction,
    closeInvitationAction,
    confirmInvitationAction,
  };
}
