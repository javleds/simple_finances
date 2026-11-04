import { computed, type ComputedRef, type Ref } from 'vue';
import { XMarkIcon } from '@heroicons/vue/24/outline';

import type { AccountGoal } from '@/modules/accounts/schemas/accountGoalSchemas';
import type { AccountGoalFormState } from '@/modules/accounts/composables/useAccountGoalModals';

type UseAccountGoalModalActionsOptions = {
    createFormState: Ref<AccountGoalFormState>;
    editFormState: Ref<AccountGoalFormState>;
    isDeleting: Ref<boolean>;
    isSaving: Ref<boolean>;
    selectedGoal: ComputedRef<AccountGoal | null>;
};

export function useAccountGoalModalActions(options: UseAccountGoalModalActionsOptions) {
    const createGoalActions = computed(() => [
        {
            key: 'close',
            label: 'Cancelar',
            tone: 'neutral' as const,
            icon: XMarkIcon,
            autoClose: true,
        },
        {
            key: 'submit-goal',
            label: options.isSaving.value ? 'Guardando...' : 'Crear meta',
            tone: 'primary' as const,
            type: 'submit' as const,
            form: 'account-goal-form',
            disabled: !options.createFormState.value.canSubmit || options.isSaving.value,
            loading: options.isSaving.value,
        },
    ]);

    const editGoalActions = computed(() => [
        {
            key: 'close',
            label: 'Cancelar',
            tone: 'neutral' as const,
            icon: XMarkIcon,
            autoClose: true,
        },
        {
            key: 'submit-edit-goal',
            label: options.isSaving.value ? 'Guardando...' : 'Guardar cambios',
            tone: 'primary' as const,
            type: 'submit' as const,
            form: 'edit-account-goal-form',
            disabled: !options.editFormState.value.canSubmit || options.isSaving.value,
            loading: options.isSaving.value,
        },
    ]);

    const deleteGoalActions = computed(() => [
        {
            key: 'close',
            label: 'Cancelar',
            tone: 'neutral' as const,
            icon: XMarkIcon,
            autoClose: true,
        },
        {
            key: 'confirm-delete-goal',
            label: options.isDeleting.value ? 'Eliminando...' : 'Eliminar meta',
            tone: 'danger' as const,
            disabled: !options.selectedGoal.value || options.isDeleting.value,
            loading: options.isDeleting.value,
        },
    ]);

    return {
        createGoalActions,
        deleteGoalActions,
        editGoalActions,
    };
}
