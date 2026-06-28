import { XMarkIcon } from '@heroicons/vue/24/outline';
import { computed, type ComputedRef, type Ref } from 'vue';

import type { AccountInvitationFormState } from '@/modules/accounts/composables/useAccountInvitationModals';
import type { AccountInvite } from '@/modules/accounts/schemas/accountInviteSchemas';

type UseAccountInvitationModalActionsOptions = {
  createFormState: Ref<AccountInvitationFormState>;
  editFormState: Ref<AccountInvitationFormState>;
  isDeleting: Ref<boolean>;
  isSaving: Ref<boolean>;
  selectedInvitation: ComputedRef<AccountInvite | null>;
};

export function useAccountInvitationModalActions(options: UseAccountInvitationModalActionsOptions) {
  const createInviteActions = computed(() => [
    { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
    {
      key: 'submit-invitation',
      label: options.isSaving.value ? 'Guardando...' : 'Crear invitación',
      tone: 'primary' as const,
      type: 'submit' as const,
      form: 'account-invitation-form',
      disabled: !options.createFormState.value.canSubmit || options.isSaving.value,
      loading: options.isSaving.value,
    },
  ]);

  const editInviteActions = computed(() => [
    { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
    {
      key: 'submit-edit-invitation',
      label: options.isSaving.value ? 'Guardando...' : 'Guardar cambios',
      tone: 'primary' as const,
      type: 'submit' as const,
      form: 'edit-account-invitation-form',
      disabled: !options.editFormState.value.canSubmit || options.isSaving.value,
      loading: options.isSaving.value,
    },
  ]);

  const deleteInviteActions = computed(() => [
    { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
    {
      key: 'confirm-delete-invitation',
      label: options.isDeleting.value ? 'Eliminando...' : 'Eliminar invitación',
      tone: 'primary' as const,
      disabled: !options.selectedInvitation.value || options.isDeleting.value,
      loading: options.isDeleting.value,
    },
  ]);

  return {
    createInviteActions,
    deleteInviteActions,
    editInviteActions,
  };
}
