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
  const isLoadingMore = ref(false);
  const isSaving = ref(false);
  const isDeleting = ref(false);
  const loadError = ref<string | null>(null);
  const saveError = ref<string | null>(null);
  const deleteError = ref<string | null>(null);
  const currentPage = ref(1);
  const lastPage = ref(1);
  const perPage = ref(20);
  const total = ref<number | null>(null);

  const hasAccounts = computed(() => accounts.value.length > 0);
  const hasMoreAccounts = computed(() => currentPage.value < lastPage.value);
  const hasReachedEnd = computed(
    () => hasAccounts.value && !hasMoreAccounts.value && !isLoadingMore.value,
  );

  async function loadAccounts(options?: { reset?: boolean; perPage?: number }): Promise<void> {
    const shouldReset = options?.reset ?? true;
    const nextPerPage = options?.perPage ?? perPage.value;

    perPage.value = nextPerPage;
    loadError.value = null;

    if (shouldReset) {
      isLoading.value = true;
    } else {
      isLoadingMore.value = true;
    }

    try {
      const response = await accountsRepository.list({
        page: 1,
        perPage: nextPerPage,
      });

      accounts.value = response.items;
      currentPage.value = response.currentPage;
      lastPage.value = response.lastPage;
      total.value = response.total;
    } catch (error) {
      loadError.value = resolveErrorMessage(error, 'No fue posible cargar las cuentas.');
    } finally {
      if (shouldReset) {
        isLoading.value = false;
      } else {
        isLoadingMore.value = false;
      }
    }
  }

  async function loadMoreAccounts(): Promise<void> {
    if (isLoading.value || isLoadingMore.value || !hasMoreAccounts.value) {
      return;
    }

    isLoadingMore.value = true;
    loadError.value = null;

    try {
      const response = await accountsRepository.list({
        page: currentPage.value + 1,
        perPage: perPage.value,
      });

      accounts.value = [...accounts.value, ...response.items];
      currentPage.value = response.currentPage;
      lastPage.value = response.lastPage;
      total.value = response.total;
    } catch (error) {
      loadError.value = resolveErrorMessage(error, 'No fue posible cargar más cuentas.');
    } finally {
      isLoadingMore.value = false;
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
    hasMoreAccounts,
    hasReachedEnd,
    isLoading,
    isLoadingMore,
    isSaving,
    isDeleting,
    loadError,
    saveError,
    deleteError,
    clearSaveError,
    clearDeleteError,
    loadAccounts,
    loadMoreAccounts,
    createAccount,
    updateAccount,
    deleteAccount,
    currentPage,
    lastPage,
    perPage,
    total,
  };
}
