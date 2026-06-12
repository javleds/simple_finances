import { computed, ref, type Ref } from 'vue';

import type { Account } from '@/modules/accounts/types';

type FormState = {
  canSubmit: boolean;
  isSubmitting: boolean;
};

type UseAccountModalsOptions = {
  accounts: Ref<Account[]>;
  clearDeleteError: () => void;
  clearSaveError: () => void;
};

const initialFormState: FormState = {
  canSubmit: false,
  isSubmitting: false,
};

export function useAccountModals(options: UseAccountModalsOptions) {
  const isCreateAccountOpen = ref(false);
  const isDeleteAccountOpen = ref(false);
  const isEditAccountOpen = ref(false);
  const isFiltersOpen = ref(false);
  const selectedAccountId = ref<string | null>(null);
  const editAccountInitialValues = ref<Partial<Account> | null>(null);
  const createFormState = ref<FormState>({ ...initialFormState });
  const editFormState = ref<FormState>({ ...initialFormState });

  const selectedAccount = computed(() => {
    if (!selectedAccountId.value) {
      return null;
    }

    return options.accounts.value.find((account) => account.id === selectedAccountId.value) ?? null;
  });

  function openFilters(): void {
    isFiltersOpen.value = true;
  }

  function closeFilters(): void {
    isFiltersOpen.value = false;
  }

  function openCreateAccount(): void {
    options.clearSaveError();
    createFormState.value = { ...initialFormState };
    isCreateAccountOpen.value = true;
  }

  function closeCreateAccount(): void {
    isCreateAccountOpen.value = false;
    options.clearSaveError();
  }

  function openEditAccount(accountId: string): void {
    const account = options.accounts.value.find((item) => item.id === accountId);

    if (!account) {
      return;
    }

    options.clearSaveError();
    selectedAccountId.value = accountId;
    editAccountInitialValues.value = {
      ...account,
      users: [...account.users],
    };
    editFormState.value = { ...initialFormState };
    isEditAccountOpen.value = true;
  }

  function closeEditAccount(): void {
    isEditAccountOpen.value = false;
    selectedAccountId.value = null;
    editAccountInitialValues.value = null;
    options.clearSaveError();
  }

  function openDeleteAccount(accountId: string): void {
    options.clearDeleteError();
    selectedAccountId.value = accountId;
    isDeleteAccountOpen.value = true;
  }

  function closeDeleteAccount(): void {
    isDeleteAccountOpen.value = false;
    selectedAccountId.value = null;
    options.clearDeleteError();
  }

  function handleCreateFormStateChange(state: FormState): void {
    createFormState.value = state;
  }

  function handleEditFormStateChange(state: FormState): void {
    editFormState.value = state;
  }

  return {
    closeCreateAccount,
    closeDeleteAccount,
    closeEditAccount,
    closeFilters,
    createFormState,
    editAccountInitialValues,
    editFormState,
    handleCreateFormStateChange,
    handleEditFormStateChange,
    isCreateAccountOpen,
    isDeleteAccountOpen,
    isEditAccountOpen,
    isFiltersOpen,
    openCreateAccount,
    openDeleteAccount,
    openEditAccount,
    openFilters,
    selectedAccount,
    selectedAccountId,
  };
}

export type { FormState as AccountFormState };
