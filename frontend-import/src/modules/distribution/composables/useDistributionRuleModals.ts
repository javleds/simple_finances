import { computed, ref, type Ref } from 'vue';

import type { DistributionRule } from '@/modules/distribution/types';

type FormState = {
  canSubmit: boolean;
  isSubmitting: boolean;
};

type UseDistributionRuleModalsOptions = {
  clearDeleteError: () => void;
  clearSaveError: () => void;
  rules: Ref<DistributionRule[]>;
};

const initialFormState: FormState = {
  canSubmit: false,
  isSubmitting: false,
};

export function useDistributionRuleModals(options: UseDistributionRuleModalsOptions) {
  const isCreateRuleOpen = ref(false);
  const isEditRuleOpen = ref(false);
  const isDeleteRuleOpen = ref(false);
  const isFiltersOpen = ref(false);
  const selectedRuleId = ref<string | null>(null);
  const createFormState = ref<FormState>({ ...initialFormState });
  const editFormState = ref<FormState>({ ...initialFormState });

  const selectedRule = computed(() => {
    if (!selectedRuleId.value) {
      return null;
    }

    return options.rules.value.find((rule) => rule.id === selectedRuleId.value) ?? null;
  });

  function openFilters(): void {
    isFiltersOpen.value = true;
  }

  function closeFilters(): void {
    isFiltersOpen.value = false;
  }

  function openCreateRule(): void {
    options.clearSaveError();
    createFormState.value = { ...initialFormState };
    isCreateRuleOpen.value = true;
  }

  function closeCreateRule(): void {
    isCreateRuleOpen.value = false;
    options.clearSaveError();
  }

  function openEditRule(ruleId: string): void {
    options.clearSaveError();
    selectedRuleId.value = ruleId;
    editFormState.value = { ...initialFormState };
    isEditRuleOpen.value = true;
  }

  function closeEditRule(): void {
    isEditRuleOpen.value = false;
    selectedRuleId.value = null;
    options.clearSaveError();
  }

  function openDeleteRule(ruleId: string): void {
    options.clearDeleteError();
    selectedRuleId.value = ruleId;
    isDeleteRuleOpen.value = true;
  }

  function closeDeleteRule(): void {
    isDeleteRuleOpen.value = false;
    selectedRuleId.value = null;
    options.clearDeleteError();
  }

  function handleCreateFormStateChange(state: FormState): void {
    createFormState.value = state;
  }

  function handleEditFormStateChange(state: FormState): void {
    editFormState.value = state;
  }

  return {
    closeCreateRule,
    closeDeleteRule,
    closeEditRule,
    closeFilters,
    createFormState,
    editFormState,
    handleCreateFormStateChange,
    handleEditFormStateChange,
    isCreateRuleOpen,
    isDeleteRuleOpen,
    isEditRuleOpen,
    isFiltersOpen,
    openCreateRule,
    openDeleteRule,
    openEditRule,
    openFilters,
    selectedRule,
  };
}

export type { FormState as DistributionRuleFormState };
