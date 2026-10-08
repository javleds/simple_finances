import { computed, ref } from 'vue';

import { usePaginatedCollection } from '@/modules/shared/composables/usePaginatedCollection';
import { resolveApiErrorMessage } from '@/modules/shared/lib/apiErrors';

import { createAccountsRepository } from '../repositories/accountsRepository';
import type { Account, AccountListFilters, AccountWritePayload } from '../types';

const accountsRepository = createAccountsRepository();

export function useAccountsCrud() {
  const accountsState = usePaginatedCollection<Account, [AccountListFilters | undefined]>({
    defaultPerPage: 20,
    loadPage: (options, filters) => accountsRepository.list({ ...options, filters }),
    resolveErrorMessage: resolveApiErrorMessage,
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

  async function loadAccounts(
    filters?: AccountListFilters,
    options?: { reset?: boolean; perPage?: number },
  ): Promise<void> {
    await accountsState.load([filters], options);
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
      saveError.value = resolveApiErrorMessage(error, 'No fue posible crear la cuenta.');
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
      saveError.value = resolveApiErrorMessage(error, 'No fue posible actualizar la cuenta.');
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
      deleteError.value = resolveApiErrorMessage(error, 'No fue posible eliminar la cuenta.');
      return false;
    } finally {
      isDeleting.value = false;
    }
  }

  async function leaveAccount(accountId: string, userId: string): Promise<boolean> {
    isDeleting.value = true;
    deleteError.value = null;

    try {
      await accountsRepository.removeUser(accountId, userId);
      accountsState.removeItem((account) => account.id === accountId);
      return true;
    } catch (error) {
      deleteError.value = resolveApiErrorMessage(error, 'No fue posible salir de la cuenta.');
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
    leaveAccount,
    currentPage: accountsState.currentPage,
    lastPage: accountsState.lastPage,
    perPage: accountsState.perPage,
    total: accountsState.total,
  };
}
