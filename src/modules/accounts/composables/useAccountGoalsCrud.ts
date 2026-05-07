import { computed, ref } from 'vue';

import { ApiError } from '@/lib/api/apiClient';

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
  const goals = ref<AccountGoal[]>([]);
  const isLoading = ref(false);
  const isSaving = ref(false);
  const isDeleting = ref(false);
  const loadError = ref<string | null>(null);
  const saveError = ref<string | null>(null);
  const deleteError = ref<string | null>(null);

  const hasGoals = computed(() => goals.value.length > 0);

  async function loadGoals(accountId: string): Promise<void> {
    isLoading.value = true;
    loadError.value = null;

    try {
      goals.value = await accountGoalsRepository.list(accountId);
    } catch (error) {
      loadError.value = resolveErrorMessage(error, 'No fue posible cargar las metas.');
    } finally {
      isLoading.value = false;
    }
  }

  async function createGoal(payload: AccountGoalWritePayload): Promise<boolean> {
    isSaving.value = true;
    saveError.value = null;

    try {
      const goal = await accountGoalsRepository.create(payload);
      goals.value = [goal, ...goals.value];
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
      goals.value = goals.value.map((goal) => (goal.id === goalId ? updatedGoal : goal));
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
      goals.value = goals.value.filter((goal) => goal.id !== goalId);
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
    goals,
    hasGoals,
    isLoading,
    isSaving,
    isDeleting,
    loadError,
    saveError,
    deleteError,
    clearSaveError,
    clearDeleteError,
    loadGoals,
    createGoal,
    updateGoal,
    deleteGoal,
  };
}
