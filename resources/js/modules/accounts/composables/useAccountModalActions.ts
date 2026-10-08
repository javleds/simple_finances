import { XMarkIcon } from '@heroicons/vue/24/outline';
import { computed, type ComputedRef, type Ref } from 'vue';

import type { AccountFormState } from '@/modules/accounts/composables/useAccountModals';
import { canLeaveAccount } from '@/modules/accounts/lib/accountPermissions';
import type { Account } from '@/modules/accounts/types';

type UseAccountModalActionsOptions = {
  createFormState: Ref<AccountFormState>;
  editFormState: Ref<AccountFormState>;
  isDeleting: Ref<boolean>;
  isSaving: Ref<boolean>;
  currentUserId: Ref<string | null>;
  selectedAccount: ComputedRef<Account | null>;
};

export function useAccountModalActions(options: UseAccountModalActionsOptions) {
  const createAccountActions = computed(() => [
    { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
    {
      key: 'submit-account',
      label: options.isSaving.value ? 'Guardando...' : 'Crear cuenta',
      tone: 'primary' as const,
      type: 'submit' as const,
      form: 'account-form',
      disabled: !options.createFormState.value.canSubmit || options.isSaving.value,
      loading: options.isSaving.value,
    },
  ]);

  const editAccountActions = computed(() => [
    { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
    {
      key: 'submit-edit-account',
      label: options.isSaving.value ? 'Guardando...' : 'Guardar cambios',
      tone: 'primary' as const,
      type: 'submit' as const,
      form: 'edit-account-form',
      disabled: !options.editFormState.value.canSubmit || options.isSaving.value,
      loading: options.isSaving.value,
    },
  ]);

  const deleteAccountActions = computed(() => [
    { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
    {
      key: 'confirm-delete-account',
      label: options.isDeleting.value
        ? 'Procesando...'
        : canLeaveAccount(options.selectedAccount.value, options.currentUserId.value)
          ? 'Salir de la cuenta'
          : 'Eliminar cuenta',
      tone: 'primary' as const,
      disabled: !options.selectedAccount.value || options.isDeleting.value,
      loading: options.isDeleting.value,
    },
  ]);

  return {
    createAccountActions,
    deleteAccountActions,
    editAccountActions,
  };
}
