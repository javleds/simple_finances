import { computed, ref } from 'vue';

import { ApiError } from '@/lib/api/apiClient';
import { usePaginatedCollection } from '@/modules/shared/composables/usePaginatedCollection';

import { createAccountGoalsRepository } from '../repositories/accountGoalsRepository';
import type { AccountGoal, AccountGoalWritePayload } from '../schemas/accountGoalSchemas';

const accountGoalsRepository = createAccountGoalsRepository();

function resolveErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof ApiError) {
    return error.message;
  }

  if (error instanceof Error && error.message) {
    return error.message;
  }

  return fallback;
}

export function useAccountGoalsCrud() {
  const goalsState = usePaginatedCollection<AccountGoal, [string]>({
    defaultPerPage: 20,
    loadPage: (options, accountId) => accountGoalsRepository.list(accountId, options),
    resolveErrorMessage,
    loadErrorMessage: 'No fue posible cargar las metas.',
    loadMoreErrorMessage: 'No fue posible cargar más metas.',
  });
  const isSaving = ref(false);
  const isDeleting = ref(false);
  const saveError = ref<string | null>(null);
  const deleteError = ref<string | null>(null);

  const hasGoals = computed(() => goalsState.hasItems.value);
  const hasMoreGoals = computed(() => goalsState.hasMoreItems.value);
  const hasReachedEnd = computed(() => goalsState.hasReachedEnd.value);

  async function loadGoals(accountId: string, options?: { reset?: boolean; perPage?: number }): Promise<void> {
    await goalsState.load([accountId], options);
  }

  async function loadMoreGoals(): Promise<void> {
    await goalsState.loadMore();
  }

  async function createGoal(payload: AccountGoalWritePayload): Promise<boolean> {
    isSaving.value = true;
    saveError.value = null;

    try {
      const goal = await accountGoalsRepository.create(payload);
      goalsState.prependItem(goal);
      return true;
    } catch (error) {
      saveError.value = resolveErrorMessage(error, 'No fue posible crear la meta.');
      return false;
    } finally {
      isSaving.value = false;
    }
  }

  async function updateGoal(goalId: string, payload: AccountGoalWritePayload): Promise<boolean> {
    isSaving.value = true;
    saveError.value = null;

    try {
      const updatedGoal = await accountGoalsRepository.update(goalId, payload);
      goalsState.replaceItem((goal) => goal.id === goalId, updatedGoal);
      return true;
    } catch (error) {
      saveError.value = resolveErrorMessage(error, 'No fue posible actualizar la meta.');
      return false;
    } finally {
      isSaving.value = false;
    }
  }

  async function deleteGoal(goalId: string, accountId?: string): Promise<boolean> {
    isDeleting.value = true;
    deleteError.value = null;

    try {
      await accountGoalsRepository.remove(goalId, accountId);
      goalsState.removeItem((goal) => goal.id === goalId);
      return true;
    } catch (error) {
      deleteError.value = resolveErrorMessage(error, 'No fue posible eliminar la meta.');
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
    goals: goalsState.items,
    hasGoals,
    hasMoreGoals,
    hasReachedEnd,
    isLoading: goalsState.isLoading,
    isLoadingMore: goalsState.isLoadingMore,
    isSaving,
    isDeleting,
    loadError: goalsState.loadError,
    saveError,
    deleteError,
    clearSaveError,
    clearDeleteError,
    loadGoals,
    loadMoreGoals,
    createGoal,
    updateGoal,
    deleteGoal,
    perPage: goalsState.perPage,
  };
}
