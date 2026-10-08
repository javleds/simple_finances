import { computed, ref } from 'vue';

import { usePaginatedCollection } from '@/modules/shared/composables/usePaginatedCollection';
import { resolveApiErrorMessage } from '@/modules/shared/lib/apiErrors';

import { createAccountGoalsRepository } from '../repositories/accountGoalsRepository';
import type {
  AccountGoal,
  AccountGoalListFilters,
  AccountGoalWritePayload,
} from '../schemas/accountGoalSchemas';

const accountGoalsRepository = createAccountGoalsRepository();

export function useAccountGoalsCrud() {
  const goalsState = usePaginatedCollection<
    AccountGoal,
    [string, AccountGoalListFilters | undefined]
  >({
    defaultPerPage: 20,
    loadPage: (options, accountId, filters) =>
      accountGoalsRepository.list(accountId, { ...options, filters }),
    resolveErrorMessage: resolveApiErrorMessage,
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

  async function loadGoals(
    accountId: string,
    filters?: AccountGoalListFilters,
    options?: { reset?: boolean; perPage?: number },
  ): Promise<void> {
    await goalsState.load([accountId, filters], options);
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
      saveError.value = resolveApiErrorMessage(error, 'No fue posible crear la meta.');
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
      saveError.value = resolveApiErrorMessage(error, 'No fue posible actualizar la meta.');
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
      deleteError.value = resolveApiErrorMessage(error, 'No fue posible eliminar la meta.');
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
