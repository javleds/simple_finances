import type { ComputedRef, Ref } from 'vue';

import type {
  AccountGoal,
  AccountGoalListFilters,
  AccountGoalWritePayload,
} from '@/modules/accounts/schemas/accountGoalSchemas';

type UseAccountGoalPageActionsOptions = {
  accountId: ComputedRef<string>;
  activeFilters: ComputedRef<AccountGoalListFilters>;
  closeCreateGoal: () => void;
  closeDeleteGoal: () => void;
  closeEditGoal: () => void;
  createGoal: (payload: AccountGoalWritePayload) => Promise<boolean>;
  deleteGoal: (goalId: string, accountId?: string) => Promise<boolean>;
  goalsPerPage: ComputedRef<number>;
  loadGoals: (
    accountId: string,
    filters?: AccountGoalListFilters,
    options?: { reset?: boolean; perPage?: number },
  ) => Promise<void>;
  loadMoreGoals: () => Promise<void>;
  selectedGoal: Ref<AccountGoal | null> | ComputedRef<AccountGoal | null>;
  updateGoal: (goalId: string, payload: AccountGoalWritePayload) => Promise<boolean>;
};

export function useAccountGoalPageActions(options: UseAccountGoalPageActionsOptions) {
  async function handleCreateGoalSubmit(payload: AccountGoalWritePayload): Promise<void> {
    const wasCreated = await options.createGoal(payload);

    if (wasCreated) {
      options.closeCreateGoal();
    }
  }

  async function handleEditGoalSubmit(payload: AccountGoalWritePayload): Promise<void> {
    if (!options.selectedGoal.value) {
      return;
    }

    const wasUpdated = await options.updateGoal(options.selectedGoal.value.id, payload);

    if (wasUpdated) {
      options.closeEditGoal();
    }
  }

  async function confirmDeleteGoal(): Promise<void> {
    if (!options.selectedGoal.value) {
      return;
    }

    const wasDeleted = await options.deleteGoal(
      options.selectedGoal.value.id,
      options.accountId.value,
    );

    if (wasDeleted) {
      options.closeDeleteGoal();
    }
  }

  function reloadGoals(): void {
    if (!options.accountId.value) {
      return;
    }

    void options.loadGoals(options.accountId.value, options.activeFilters.value, {
      reset: true,
      perPage: options.goalsPerPage.value,
    });
  }

  function handleLoadMoreRetry(): void {
    void options.loadMoreGoals();
  }

  return {
    confirmDeleteGoal,
    handleCreateGoalSubmit,
    handleEditGoalSubmit,
    handleLoadMoreRetry,
    reloadGoals,
  };
}
