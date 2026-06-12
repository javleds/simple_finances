import { CheckCircleIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { computed, type ComputedRef, type Ref } from 'vue';

import type { AccountPendingByUser } from '@/modules/accounts/types';
import type { Transaction } from '@/modules/transactions/types';

type FormState = {
  canSubmit: boolean;
  isSubmitting: boolean;
};

type UseAccountTransactionModalActionsOptions = {
  createFormState: Ref<FormState>;
  editFormState: Ref<FormState>;
  isCompletingPendingByUser: Ref<boolean>;
  isDeleting: Ref<boolean>;
  isSaving: Ref<boolean>;
  selectedPendingByUser: ComputedRef<AccountPendingByUser | null>;
  selectedTransaction: ComputedRef<Transaction | null>;
};

export function useAccountTransactionModalActions(
  options: UseAccountTransactionModalActionsOptions,
) {
  const createTransactionActions = computed(() => [
    { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
    {
      key: 'submit-transaction',
      label: options.isSaving.value ? 'Guardando...' : 'Crear transacción',
      tone: 'primary' as const,
      type: 'submit' as const,
      form: 'transaction-form',
      disabled: !options.createFormState.value.canSubmit || options.isSaving.value,
    },
  ]);

  const editTransactionActions = computed(() => [
    { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
    {
      key: 'submit-edit-transaction',
      label: options.isSaving.value ? 'Guardando...' : 'Guardar cambios',
      tone: 'primary' as const,
      type: 'submit' as const,
      form: 'edit-transaction-form',
      disabled: !options.editFormState.value.canSubmit || options.isSaving.value,
    },
  ]);

  const deleteTransactionActions = computed(() => [
    { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
    {
      key: 'confirm-delete-transaction',
      label: options.isDeleting.value ? 'Eliminando...' : 'Eliminar transacción',
      tone: 'primary' as const,
      disabled: !options.selectedTransaction.value || options.isDeleting.value,
    },
  ]);

  const completeTransactionActions = computed(() => [
    { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
    {
      key: 'confirm-complete-transaction',
      label: options.isSaving.value ? 'Guardando...' : 'Completar transacción',
      tone: 'primary' as const,
      icon: CheckCircleIcon,
      disabled: !options.selectedTransaction.value || options.isSaving.value,
    },
  ]);

  const completePendingByUserActions = computed(() => [
    { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
    {
      key: 'confirm-complete-pending-by-user',
      label: options.isCompletingPendingByUser.value ? 'Completando...' : 'Completar pendientes',
      tone: 'primary' as const,
      icon: CheckCircleIcon,
      disabled: !options.selectedPendingByUser.value || options.isCompletingPendingByUser.value,
    },
  ]);

  return {
    completePendingByUserActions,
    completeTransactionActions,
    createTransactionActions,
    deleteTransactionActions,
    editTransactionActions,
  };
}

export type { FormState as AccountTransactionFormState };
