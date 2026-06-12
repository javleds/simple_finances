import { XMarkIcon } from '@heroicons/vue/24/outline';
import { computed, type ComputedRef, type Ref } from 'vue';

import type { AccountMember } from '@/modules/accounts/types';

type UseAccountUserModalActionsOptions = {
  canSubmitPercentage: () => boolean;
  isDeleting: Ref<boolean>;
  isSaving: Ref<boolean>;
  selectedUser: ComputedRef<AccountMember | null>;
};

const createUserActions = [
  { key: 'close', label: 'Cerrar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
];

export function useAccountUserModalActions(options: UseAccountUserModalActionsOptions) {
  const editUserActions = computed(() => [
    { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
    {
      key: 'submit-edit-user',
      label: options.isSaving.value ? 'Guardando...' : 'Guardar porcentaje',
      tone: 'primary' as const,
      type: 'button' as const,
      disabled: !options.canSubmitPercentage() || options.isSaving.value,
    },
  ]);

  const deleteUserActions = computed(() => [
    { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
    {
      key: 'confirm-delete-user',
      label: options.isDeleting.value ? 'Eliminando...' : 'Quitar usuario',
      tone: 'primary' as const,
      disabled: !options.selectedUser.value || options.isDeleting.value,
    },
  ]);

  return {
    createUserActions,
    deleteUserActions,
    editUserActions,
  };
}
