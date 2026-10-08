import { XMarkIcon } from '@heroicons/vue/24/outline';
import { computed, type ComputedRef, type Ref } from 'vue';

import type { Transaction } from '@/modules/transactions/types';

type FormState = {
  canSubmit: boolean;
  isSubmitting: boolean;
  keepOpen: boolean;
};

type UseAccountTransactionModalActionsOptions = {
  createFormState: Ref<FormState>;
  editFormState: Ref<FormState>;
  isDeleting: Ref<boolean>;
  isSaving: Ref<boolean>;
  selectedTransaction: ComputedRef<Transaction | null>;
};

export function useAccountTransactionModalActions(
  options: UseAccountTransactionModalActionsOptions,
) {
  const createTransactionActions = computed(() => [
    { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
    {
      key: 'submit-transaction',
      label: options.isSaving.value
        ? 'Guardando...'
        : options.createFormState.value.keepOpen
          ? 'Crear y agregar otro'
          : 'Crear transacción',
      tone: 'primary' as const,
      type: 'submit' as const,
      form: 'transaction-form',
      disabled: !options.createFormState.value.canSubmit || options.isSaving.value,
      loading: options.isSaving.value,
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
      loading: options.isSaving.value,
    },
  ]);

  const deleteTransactionActions = computed(() => [
    { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
    {
      key: 'confirm-delete-transaction',
      label: options.isDeleting.value ? 'Eliminando...' : 'Eliminar transacción',
      tone: 'primary' as const,
      disabled: !options.selectedTransaction.value || options.isDeleting.value,
      loading: options.isDeleting.value,
    },
  ]);

  return {
    createTransactionActions,
    deleteTransactionActions,
    editTransactionActions,
  };
}

export type { FormState as AccountTransactionFormState };
