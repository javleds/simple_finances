import { XMarkIcon } from '@heroicons/vue/24/outline';
import { computed, type ComputedRef, type Ref } from 'vue';

import type { DistributionRelationFormState } from '@/modules/distribution/composables/useDistributionRelationModals';
import type { DistributionRelation } from '@/modules/distribution/types';

type UseDistributionRelationModalActionsOptions = {
  createFormState: Ref<DistributionRelationFormState>;
  editFormState: Ref<DistributionRelationFormState>;
  isDeleting: Ref<boolean>;
  isSaving: Ref<boolean>;
  selectedRelation: ComputedRef<DistributionRelation | null>;
};

export function useDistributionRelationModalActions(
  options: UseDistributionRelationModalActionsOptions,
) {
  const createRelationActions = computed(() => [
    { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
    {
      key: 'submit-relation',
      label: options.isSaving.value ? 'Guardando...' : 'Crear relación',
      tone: 'primary' as const,
      type: 'submit' as const,
      form: 'distribution-relation-form',
      disabled: !options.createFormState.value.canSubmit || options.isSaving.value,
    },
  ]);

  const editRelationActions = computed(() => [
    { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
    {
      key: 'submit-edit-relation',
      label: options.isSaving.value ? 'Guardando...' : 'Guardar cambios',
      tone: 'primary' as const,
      type: 'submit' as const,
      form: 'edit-distribution-relation-form',
      disabled: !options.editFormState.value.canSubmit || options.isSaving.value,
    },
  ]);

  const deleteRelationActions = computed(() => [
    { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
    {
      key: 'confirm-delete-relation',
      label: options.isDeleting.value ? 'Eliminando...' : 'Eliminar relación',
      tone: 'primary' as const,
      disabled: !options.selectedRelation.value || options.isDeleting.value,
    },
  ]);

  return {
    createRelationActions,
    deleteRelationActions,
    editRelationActions,
  };
}
