import { XMarkIcon } from '@heroicons/vue/24/outline';
import { computed, type ComputedRef, type Ref } from 'vue';

import type { SubscriptionFormState } from '@/modules/subscriptions/composables/useSubscriptionModals';
import type { Subscription } from '@/modules/subscriptions/types';

type UseSubscriptionModalActionsOptions = {
  createFormState: Ref<SubscriptionFormState>;
  editFormState: Ref<SubscriptionFormState>;
  isDeleting: Ref<boolean>;
  isSaving: Ref<boolean>;
  selectedSubscription: ComputedRef<Subscription | null>;
};

export function useSubscriptionModalActions(options: UseSubscriptionModalActionsOptions) {
  const createSubscriptionActions = computed(() => [
    { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
    {
      key: 'submit-subscription',
      label: options.isSaving.value ? 'Guardando...' : 'Crear suscripción',
      tone: 'primary' as const,
      type: 'submit' as const,
      form: 'subscription-form',
      disabled: !options.createFormState.value.canSubmit || options.isSaving.value,
      loading: options.isSaving.value,
    },
  ]);

  const editSubscriptionActions = computed(() => [
    { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
    {
      key: 'submit-edit-subscription',
      label: options.isSaving.value ? 'Guardando...' : 'Guardar cambios',
      tone: 'primary' as const,
      type: 'submit' as const,
      form: 'edit-subscription-form',
      disabled: !options.editFormState.value.canSubmit || options.isSaving.value,
      loading: options.isSaving.value,
    },
  ]);

  const deleteSubscriptionActions = computed(() => [
    { key: 'close', label: 'Cancelar', tone: 'danger' as const, icon: XMarkIcon, autoClose: true },
    {
      key: 'confirm-delete-subscription',
      label: options.isDeleting.value ? 'Eliminando...' : 'Eliminar suscripción',
      tone: 'primary' as const,
      disabled: !options.selectedSubscription.value || options.isDeleting.value,
      loading: options.isDeleting.value,
    },
  ]);

  return {
    createSubscriptionActions,
    deleteSubscriptionActions,
    editSubscriptionActions,
  };
}
