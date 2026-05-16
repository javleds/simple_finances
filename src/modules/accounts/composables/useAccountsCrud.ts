import { computed, ref } from 'vue';

import { ApiError } from '@/lib/api/apiClient';
import { usePaginatedCollection } from '@/modules/shared/composables/usePaginatedCollection';

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
  const accountsState = usePaginatedCollection<Account, []>({
    defaultPerPage: 20,
    loadPage: (options) => accountsRepository.list(options),
    resolveErrorMessage,
    loadErrorMessage: 'No fue posible cargar las cuentas.',
    loadMoreErrorMessage: 'No fue posible cargar más cuentas.',
  });
  const isSaving = ref(false);
  const isDeleting = ref(false);
  const saveError = ref<string | null>(null);
  const deleteError = ref<string | null>(null);

  const hasAccounts = computed(() => accountsState.hasItems.value);
  const hasMoreAccounts = computed(() => accountsState.hasMoreItems.value);
  const hasReachedEnd = computed(() => accountsState.hasReachedEnd.value);

  async function loadAccounts(options?: { reset?: boolean; perPage?: number }): Promise<void> {
    await accountsState.load([], options);
  }

  async function loadMoreAccounts(): Promise<void> {
    await accountsState.loadMore();
  }

  async function createAccount(payload: AccountWritePayload): Promise<boolean> {
    isSaving.value = true;
    saveError.value = null;

    try {
      const account = await accountsRepository.create(payload);
      accountsState.prependItem(account);
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
      accountsState.replaceItem((account) => account.id === accountId, updatedAccount);
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
      accountsState.removeItem((account) => account.id === accountId);
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
    accounts: accountsState.items,
    hasAccounts,
    hasMoreAccounts,
    hasReachedEnd,
    isLoading: accountsState.isLoading,
    isLoadingMore: accountsState.isLoadingMore,
    isSaving,
    isDeleting,
    loadError: accountsState.loadError,
    saveError,
    deleteError,
    clearSaveError,
    clearDeleteError,
    loadAccounts,
    loadMoreAccounts,
    createAccount,
    updateAccount,
    deleteAccount,
    currentPage: accountsState.currentPage,
    lastPage: accountsState.lastPage,
    perPage: accountsState.perPage,
    total: accountsState.total,
  };
}
