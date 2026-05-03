import { computed, ref } from 'vue';

import { ApiError } from '@/lib/api/apiClient';

import { createAccountsRepository } from '../repositories/accountsRepository';
import type { Account, AccountWritePayload } from '../types';

const accountsRepository = createAccountsRepository();

function resolveErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof ApiError) {
    return error.message;
  }

  if (error instanceof Error && error.message) {
    return error.message;
  }

  return fallback;
}

export function useAccountsCrud() {
  const accounts = ref<Account[]>([]);
  const isLoading = ref(false);
  const isSaving = ref(false);
  const isDeleting = ref(false);
  const loadError = ref<string | null>(null);
  const saveError = ref<string | null>(null);
  const deleteError = ref<string | null>(null);

  const hasAccounts = computed(() => accounts.value.length > 0);

  async function loadAccounts(): Promise<void> {
    isLoading.value = true;
    loadError.value = null;

    try {
      accounts.value = await accountsRepository.list();
    } catch (error) {
      loadError.value = resolveErrorMessage(error, 'No fue posible cargar las cuentas.');
    } finally {
      isLoading.value = false;
    }
  }

  async function createAccount(payload: AccountWritePayload): Promise<boolean> {
    isSaving.value = true;
    saveError.value = null;

    try {
      const account = await accountsRepository.create(payload);
      accounts.value = [account, ...accounts.value];
      return true;
    } catch (error) {
      saveError.value = resolveErrorMessage(error, 'No fue posible crear la cuenta.');
      return false;
    } finally {
      isSaving.value = false;
    }
  }

  async function updateAccount(accountId: string, payload: AccountWritePayload): Promise<boolean> {
    isSaving.value = true;
    saveError.value = null;

    try {
      const updatedAccount = await accountsRepository.update(accountId, payload);
      accounts.value = accounts.value.map((account) =>
        account.id === accountId ? updatedAccount : account,
      );
      return true;
    } catch (error) {
      saveError.value = resolveErrorMessage(error, 'No fue posible actualizar la cuenta.');
      return false;
    } finally {
      isSaving.value = false;
    }
  }

  async function deleteAccount(accountId: string): Promise<boolean> {
    isDeleting.value = true;
    deleteError.value = null;

    try {
      await accountsRepository.remove(accountId);
      accounts.value = accounts.value.filter((account) => account.id !== accountId);
      return true;
    } catch (error) {
      deleteError.value = resolveErrorMessage(error, 'No fue posible eliminar la cuenta.');
      return false;
    } finally {
      isDeleting.value = false;
    }
  }

  function clearSaveError(): void {
    saveError.value = null;
  }

  function clearDeleteError(): void {
    deleteError.value = null;
  }

  return {
    accounts,
    hasAccounts,
    isLoading,
    isSaving,
    isDeleting,
    loadError,
    saveError,
    deleteError,
    clearSaveError,
    clearDeleteError,
    loadAccounts,
    createAccount,
    updateAccount,
    deleteAccount,
  };
}
