import { computed, ref, type Ref } from 'vue';

import type { Transaction } from '@/modules/transactions/types';

type FormState = {
  canSubmit: boolean;
  isSubmitting: boolean;
};

type UseAccountTransactionModalsOptions = {
  clearDeleteError: () => void;
  clearSaveError: () => void;
  loadGoals: () => void;
  transactions: Ref<Transaction[]>;
};

const initialFormState: FormState = {
  canSubmit: false,
  isSubmitting: false,
};

export function useAccountTransactionModals(options: UseAccountTransactionModalsOptions) {
  const isCreateTransactionModalOpen = ref(false);
  const isEditTransactionModalOpen = ref(false);
  const isDeleteTransactionModalOpen = ref(false);
  const isCompleteTransactionModalOpen = ref(false);
  const isFiltersOpen = ref(false);
  const selectedTransactionId = ref<string | null>(null);
  const createFormState = ref<FormState>({ ...initialFormState });
  const editFormState = ref<FormState>({ ...initialFormState });

  const selectedTransaction = computed(() => {
    if (!selectedTransactionId.value) {
      return null;
    }

    return (
      options.transactions.value.find(
        (transaction) => transaction.id === selectedTransactionId.value,
      ) ?? null
    );
  });

  function openCreateTransactionModal(): void {
    options.clearSaveError();
    createFormState.value = { ...initialFormState };
    options.loadGoals();
    isCreateTransactionModalOpen.value = true;
  }

  function closeCreateTransactionModal(): void {
    isCreateTransactionModalOpen.value = false;
    options.clearSaveError();
  }

  function openEditTransaction(transactionId: string): void {
    options.clearSaveError();
    selectedTransactionId.value = transactionId;
    editFormState.value = { ...initialFormState };
    options.loadGoals();
    isEditTransactionModalOpen.value = true;
  }

  function closeEditTransactionModal(): void {
    isEditTransactionModalOpen.value = false;
    selectedTransactionId.value = null;
    options.clearSaveError();
  }

  function openDeleteTransaction(transactionId: string): void {
    options.clearDeleteError();
    selectedTransactionId.value = transactionId;
    isDeleteTransactionModalOpen.value = true;
  }

  function closeDeleteTransactionModal(): void {
    isDeleteTransactionModalOpen.value = false;
    selectedTransactionId.value = null;
    options.clearDeleteError();
  }

  function openCompleteTransaction(transactionId: string): void {
    options.clearSaveError();
    selectedTransactionId.value = transactionId;
    isCompleteTransactionModalOpen.value = true;
  }

  function closeCompleteTransactionModal(): void {
    isCompleteTransactionModalOpen.value = false;
    selectedTransactionId.value = null;
    options.clearSaveError();
  }

  function openFilters(): void {
    isFiltersOpen.value = true;
  }

  function closeFilters(): void {
    isFiltersOpen.value = false;
  }

  function handleCreateFormStateChange(state: FormState): void {
    createFormState.value = state;
  }

  function handleEditFormStateChange(state: FormState): void {
    editFormState.value = state;
  }

  return {
    closeCreateTransactionModal,
    closeDeleteTransactionModal,
    closeEditTransactionModal,
    closeCompleteTransactionModal,
    closeFilters,
    createFormState,
    editFormState,
    handleCreateFormStateChange,
    handleEditFormStateChange,
    isCreateTransactionModalOpen,
    isDeleteTransactionModalOpen,
    isEditTransactionModalOpen,
    isCompleteTransactionModalOpen,
    isFiltersOpen,
    openCreateTransactionModal,
    openDeleteTransaction,
    openEditTransaction,
    openCompleteTransaction,
    openFilters,
    selectedTransaction,
  };
}

export type { FormState as AccountTransactionFormState };
