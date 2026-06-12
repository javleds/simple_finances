import { computed, ref, type Ref } from 'vue';

import type { AccountInvite } from '@/modules/accounts/schemas/accountInviteSchemas';

type FormState = {
  canSubmit: boolean;
  isSubmitting: boolean;
};

type UseAccountInvitationModalsOptions = {
  clearDeleteError: () => void;
  clearSaveError: () => void;
  invitations: Ref<AccountInvite[]>;
};

const initialFormState: FormState = {
  canSubmit: false,
  isSubmitting: false,
};

export function useAccountInvitationModals(options: UseAccountInvitationModalsOptions) {
  const isFiltersOpen = ref(false);
  const isCreateInvitationOpen = ref(false);
  const isEditInvitationOpen = ref(false);
  const isDeleteInvitationOpen = ref(false);
  const selectedInvitationId = ref<string | null>(null);
  const createFormState = ref<FormState>({ ...initialFormState });
  const editFormState = ref<FormState>({ ...initialFormState });

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

  function openFilters(): void {
    isFiltersOpen.value = true;
  }

  function closeFilters(): void {
    isFiltersOpen.value = false;
  }

  function openCreateInvitation(): void {
    options.clearSaveError();
    createFormState.value = { ...initialFormState };
    isCreateInvitationOpen.value = true;
  }

  function closeCreateInvitation(): void {
    isCreateInvitationOpen.value = false;
    options.clearSaveError();
  }

  function openEditInvitation(invitationId: string): void {
    options.clearSaveError();
    selectedInvitationId.value = invitationId;
    editFormState.value = { ...initialFormState };
    isEditInvitationOpen.value = true;
  }

  function closeEditInvitation(): void {
    isEditInvitationOpen.value = false;
    selectedInvitationId.value = null;
    options.clearSaveError();
  }

  function openDeleteInvitation(invitationId: string): void {
    options.clearDeleteError();
    selectedInvitationId.value = invitationId;
    isDeleteInvitationOpen.value = true;
  }

  function closeDeleteInvitation(): void {
    isDeleteInvitationOpen.value = false;
    selectedInvitationId.value = null;
    options.clearDeleteError();
  }

  function handleCreateFormStateChange(state: FormState): void {
    createFormState.value = state;
  }

  function handleEditFormStateChange(state: FormState): void {
    editFormState.value = state;
  }

  return {
    closeCreateInvitation,
    closeDeleteInvitation,
    closeEditInvitation,
    closeFilters,
    createFormState,
    editFormState,
    handleCreateFormStateChange,
    handleEditFormStateChange,
    isCreateInvitationOpen,
    isDeleteInvitationOpen,
    isEditInvitationOpen,
    isFiltersOpen,
    openCreateInvitation,
    openDeleteInvitation,
    openEditInvitation,
    openFilters,
    selectedInvitation,
  };
}

export type { FormState as AccountInvitationFormState };
