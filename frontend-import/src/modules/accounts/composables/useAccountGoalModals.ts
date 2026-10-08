import { computed, ref, type Ref } from 'vue';

import type { AccountGoal } from '@/modules/accounts/schemas/accountGoalSchemas';

type FormState = {
  canSubmit: boolean;
  isSubmitting: boolean;
};

type UseAccountGoalModalsOptions = {
  goals: Ref<AccountGoal[]>;
  clearDeleteError: () => void;
  clearSaveError: () => void;
};

const initialFormState: FormState = {
  canSubmit: false,
  isSubmitting: false,
};

export function useAccountGoalModals(options: UseAccountGoalModalsOptions) {
  const isFiltersOpen = ref(false);
  const isCreateGoalOpen = ref(false);
  const isEditGoalOpen = ref(false);
  const isDeleteGoalOpen = ref(false);
  const selectedGoalId = ref<string | null>(null);
  const createFormState = ref<FormState>({ ...initialFormState });
  const editFormState = ref<FormState>({ ...initialFormState });

  const selectedGoal = computed(() => {
    if (!selectedGoalId.value) {
      return null;
    }

    return options.goals.value.find((goal) => goal.id === selectedGoalId.value) ?? null;
  });

  function openFilters(): void {
    isFiltersOpen.value = true;
  }

  function closeFilters(): void {
    isFiltersOpen.value = false;
  }

  function openCreateGoal(): void {
    options.clearSaveError();
    createFormState.value = { ...initialFormState };
    isCreateGoalOpen.value = true;
  }

  function closeCreateGoal(): void {
    isCreateGoalOpen.value = false;
    options.clearSaveError();
  }

  function openEditGoal(goalId: string): void {
    options.clearSaveError();
    selectedGoalId.value = goalId;
    editFormState.value = { ...initialFormState };
    isEditGoalOpen.value = true;
  }

  function closeEditGoal(): void {
    isEditGoalOpen.value = false;
    selectedGoalId.value = null;
    options.clearSaveError();
  }

  function openDeleteGoal(goalId: string): void {
    options.clearDeleteError();
    selectedGoalId.value = goalId;
    isDeleteGoalOpen.value = true;
  }

  function closeDeleteGoal(): void {
    isDeleteGoalOpen.value = false;
    selectedGoalId.value = null;
    options.clearDeleteError();
  }

  function handleCreateFormStateChange(state: FormState): void {
    createFormState.value = state;
  }

  function handleEditFormStateChange(state: FormState): void {
    editFormState.value = state;
  }

  return {
    closeCreateGoal,
    closeDeleteGoal,
    closeEditGoal,
    closeFilters,
    createFormState,
    editFormState,
    handleCreateFormStateChange,
    handleEditFormStateChange,
    isCreateGoalOpen,
    isDeleteGoalOpen,
    isEditGoalOpen,
    isFiltersOpen,
    openCreateGoal,
    openDeleteGoal,
    openEditGoal,
    openFilters,
    selectedGoal,
  };
}

export type { FormState as AccountGoalFormState };
