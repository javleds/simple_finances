import { computed, ref, type Ref } from 'vue';

import type { Transaction } from '@/modules/transactions/types';

type FormState = {
  canSubmit: boolean;
  isSubmitting: boolean;
  keepOpen: boolean;
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
  keepOpen: false,
};

export function useAccountTransactionModals(options: UseAccountTransactionModalsOptions) {
  const isCreateTransactionModalOpen = ref(false);
  const isEditTransactionModalOpen = ref(false);
  const isDeleteTransactionModalOpen = ref(false);
  const isFiltersOpen = ref(false);
  const selectedTransactionId = ref<string | null>(null);
  const createInitialValues = ref<Partial<Transaction> | null>(null);
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
    createInitialValues.value = null;
    createFormState.value = { ...initialFormState };
    options.loadGoals();
    isCreateTransactionModalOpen.value = true;
  }

  function closeCreateTransactionModal(): void {
    isCreateTransactionModalOpen.value = false;
    createInitialValues.value = null;
    options.clearSaveError();
  }

  function prepareNextCreateTransaction(payload: {
    accountId: string;
    date: string;
    financialGoalId: string | null;
    type: Transaction['type'];
  }): void {
    createInitialValues.value = {
      accountId: payload.accountId,
      date: payload.date,
      financialGoalId: payload.type === 'income' ? payload.financialGoalId : null,
      type: payload.type,
      userPayments: {},
    };
    createFormState.value = { ...initialFormState, keepOpen: true };
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
    closeFilters,
    createInitialValues,
    createFormState,
    editFormState,
    handleCreateFormStateChange,
    handleEditFormStateChange,
    isCreateTransactionModalOpen,
    isDeleteTransactionModalOpen,
    isEditTransactionModalOpen,
    isFiltersOpen,
    openCreateTransactionModal,
    openDeleteTransaction,
    openEditTransaction,
    openFilters,
    prepareNextCreateTransaction,
    selectedTransaction,
  };
}

export type { FormState as AccountTransactionFormState };
