import { XMarkIcon } from '@heroicons/vue/24/outline';
import { computed, type ComputedRef, type Ref } from 'vue';

import type { DistributionRuleFormState } from '@/modules/distribution/composables/useDistributionRuleModals';
import type { DistributionRule } from '@/modules/distribution/types';

type UseDistributionRuleModalActionsOptions = {
  createFormState: Ref<DistributionRuleFormState>;
  editFormState: Ref<DistributionRuleFormState>;
  isDeleting: Ref<boolean>;
  isSaving: Ref<boolean>;
  selectedRule: ComputedRef<DistributionRule | null>;
};

export function useDistributionRuleModalActions(options: UseDistributionRuleModalActionsOptions) {
  const createRuleActions = computed(() => [
    {
      key: 'close',
      label: 'Cancelar',
      tone: 'neutral' as const,
      icon: XMarkIcon,
      autoClose: true,
    },
    {
      key: 'submit-rule',
      label: options.isSaving.value ? 'Guardando...' : 'Crear regla',
      tone: 'primary' as const,
      type: 'submit' as const,
      form: 'distribution-rule-form',
      disabled: !options.createFormState.value.canSubmit || options.isSaving.value,
      loading: options.isSaving.value,
    },
  ]);

  const editRuleActions = computed(() => [
    {
      key: 'close',
      label: 'Cancelar',
      tone: 'neutral' as const,
      icon: XMarkIcon,
      autoClose: true,
    },
    {
      key: 'submit-edit-rule',
      label: options.isSaving.value ? 'Guardando...' : 'Guardar cambios',
      tone: 'primary' as const,
      type: 'submit' as const,
      form: 'edit-distribution-rule-form',
      disabled: !options.editFormState.value.canSubmit || options.isSaving.value,
      loading: options.isSaving.value,
    },
  ]);

  const deleteRuleActions = computed(() => [
    {
      key: 'close',
      label: 'Cancelar',
      tone: 'neutral' as const,
      icon: XMarkIcon,
      autoClose: true,
    },
    {
      key: 'confirm-delete-rule',
      label: options.isDeleting.value ? 'Eliminando...' : 'Eliminar regla',
      tone: 'danger' as const,
      disabled: !options.selectedRule.value || options.isDeleting.value,
      loading: options.isDeleting.value,
    },
  ]);

  return {
    createRuleActions,
    deleteRuleActions,
    editRuleActions,
  };
}
