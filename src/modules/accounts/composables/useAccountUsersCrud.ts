import { computed, ref, type Ref } from 'vue';

import { ApiError } from '@/lib/api/apiClient';
import { createAccountsRepository } from '@/modules/accounts/repositories/accountsRepository';
import type {
  AccountMember,
  AccountUsersListFilters,
} from '@/modules/accounts/types';
import { usePaginatedCollection } from '@/modules/shared/composables/usePaginatedCollection';

const accountsRepository = createAccountsRepository();
const defaultUsersPerPage = 20;

function resolveErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof ApiError) {
    return error.message;
  }

  if (error instanceof Error && error.message) {
    return error.message;
  }

  return fallback;
}

export function useAccountUsersCrud(filters: Ref<AccountUsersListFilters>) {
  const usersState = usePaginatedCollection<AccountMember, [string]>({
    defaultPerPage: defaultUsersPerPage,
    loadPage: (options, accountId) =>
      accountsRepository.listUsers(accountId, {
        ...options,
        filters: filters.value,
      }),
    resolveErrorMessage,
    loadErrorMessage: 'No fue posible cargar los usuarios.',
    loadMoreErrorMessage: 'No fue posible cargar más usuarios.',
  });
  const isSaving = ref(false);
  const isDeleting = ref(false);
  const saveError = ref<string | null>(null);
  const deleteError = ref<string | null>(null);

  const hasUsers = computed(() => usersState.hasItems.value);
  const hasMoreUsers = computed(() => usersState.hasMoreItems.value);
  const hasReachedEnd = computed(() => usersState.hasReachedEnd.value);

  async function loadUsers(
    accountId: string,
    options?: { reset?: boolean; perPage?: number },
  ): Promise<void> {
    await usersState.load([accountId], options);
  }

  async function loadMoreUsers(): Promise<void> {
    await usersState.loadMore();
  }

  async function updateUserPercentage(
    accountId: string,
    userId: string,
    percentage: number,
  ): Promise<boolean> {
    isSaving.value = true;
    saveError.value = null;

    try {
      const updatedUser = await accountsRepository.updateUserPercentage(accountId, userId, percentage);
      usersState.replaceItem((user) => user.id === updatedUser.id, updatedUser);
      return true;
    } catch (error) {
      saveError.value = resolveErrorMessage(error, 'No fue posible actualizar el porcentaje.');
      return false;
    } finally {
      isSaving.value = false;
    }
  }

  async function removeUser(accountId: string, userId: string): Promise<boolean> {
    isDeleting.value = true;
    deleteError.value = null;

    try {
      await accountsRepository.removeUser(accountId, userId);
      usersState.removeItem((user) => user.id === userId);
      return true;
    } catch (error) {
      deleteError.value = resolveErrorMessage(error, 'No fue posible quitar al usuario.');
      return false;
    } finally {
      isDeleting.value = false;
    }
  }

  function setUsers(users: AccountMember[]): void {
    usersState.setItems(users);
  }

  function clearSaveError(): void {
    saveError.value = null;
  }

  function clearDeleteError(): void {
    deleteError.value = null;
  }

  return {
    clearDeleteError,
    clearSaveError,
    deleteError,
    hasMoreUsers,
    hasReachedEnd,
    hasUsers,
    isDeleting,
    isLoading: usersState.isLoading,
    isLoadingMore: usersState.isLoadingMore,
    isSaving,
    loadError: usersState.loadError,
    loadMoreUsers,
    loadUsers,
    removeUser,
    saveError,
    setUsers,
    updateUserPercentage,
    users: usersState.items,
  };
}
