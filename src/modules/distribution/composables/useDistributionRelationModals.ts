import { computed, ref, type Ref } from 'vue';

import type { DistributionRelation } from '@/modules/distribution/types';

type FormState = {
  canSubmit: boolean;
  isSubmitting: boolean;
};

type UseDistributionRelationModalsOptions = {
  clearDeleteError: () => void;
  clearSaveError: () => void;
  relations: Ref<DistributionRelation[]>;
};

const initialFormState: FormState = {
  canSubmit: false,
  isSubmitting: false,
};

export function useDistributionRelationModals(options: UseDistributionRelationModalsOptions) {
  const isCreateRelationOpen = ref(false);
  const isEditRelationOpen = ref(false);
  const isDeleteRelationOpen = ref(false);
  const selectedRelationId = ref<string | null>(null);
  const createFormState = ref<FormState>({ ...initialFormState });
  const editFormState = ref<FormState>({ ...initialFormState });

  const selectedRelation = computed(() => {
    if (!selectedRelationId.value) {
      return null;
    }

    return (
      options.relations.value.find((relation) => relation.id === selectedRelationId.value) ?? null
    );
  });

  function openCreateRelation(): void {
    options.clearSaveError();
    createFormState.value = { ...initialFormState };
    isCreateRelationOpen.value = true;
  }

  function closeCreateRelation(): void {
    isCreateRelationOpen.value = false;
    options.clearSaveError();
  }

  function openEditRelation(relationId: string): void {
    options.clearSaveError();
    selectedRelationId.value = relationId;
    editFormState.value = { ...initialFormState };
    isEditRelationOpen.value = true;
  }

  function closeEditRelation(): void {
    isEditRelationOpen.value = false;
    selectedRelationId.value = null;
    options.clearSaveError();
  }

  function openDeleteRelation(relationId: string): void {
    options.clearDeleteError();
    selectedRelationId.value = relationId;
    isDeleteRelationOpen.value = true;
  }

  function closeDeleteRelation(): void {
    isDeleteRelationOpen.value = false;
    selectedRelationId.value = null;
    options.clearDeleteError();
  }

  function handleCreateFormStateChange(state: FormState): void {
    createFormState.value = state;
  }

  function handleEditFormStateChange(state: FormState): void {
    editFormState.value = state;
  }

  return {
    closeCreateRelation,
    closeDeleteRelation,
    closeEditRelation,
    createFormState,
    editFormState,
    handleCreateFormStateChange,
    handleEditFormStateChange,
    isCreateRelationOpen,
    isDeleteRelationOpen,
    isEditRelationOpen,
    openCreateRelation,
    openDeleteRelation,
    openEditRelation,
    selectedRelation,
  };
}

export type { FormState as DistributionRelationFormState };
