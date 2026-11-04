import { computed, ref, type Ref } from 'vue';

import type { Subscription } from '@/modules/subscriptions/types';

type FormState = {
  canSubmit: boolean;
  isSubmitting: boolean;
};

type UseSubscriptionModalsOptions = {
  clearDeleteError: () => void;
  clearSaveError: () => void;
  subscriptions: Ref<Subscription[]>;
};

const initialFormState: FormState = {
  canSubmit: false,
  isSubmitting: false,
};

export function useSubscriptionModals(options: UseSubscriptionModalsOptions) {
  const isFiltersOpen = ref(false);
  const isCreateSubscriptionOpen = ref(false);
  const isEditSubscriptionOpen = ref(false);
  const isDeleteSubscriptionOpen = ref(false);
  const selectedSubscriptionId = ref<string | null>(null);
  const createFormState = ref<FormState>({ ...initialFormState });
  const editFormState = ref<FormState>({ ...initialFormState });

  const selectedSubscription = computed(() => {
    if (!selectedSubscriptionId.value) {
      return null;
    }

    return (
      options.subscriptions.value.find(
        (subscription) => subscription.id === selectedSubscriptionId.value,
      ) ?? null
    );
  });

  function openFilters(): void {
    isFiltersOpen.value = true;
  }

  function closeFilters(): void {
    isFiltersOpen.value = false;
  }

  function openCreateSubscription(): void {
    options.clearSaveError();
    createFormState.value = { ...initialFormState };
    isCreateSubscriptionOpen.value = true;
  }

  function closeCreateSubscription(): void {
    isCreateSubscriptionOpen.value = false;
    options.clearSaveError();
  }

  function openEditSubscription(subscriptionId: string): void {
    options.clearSaveError();
    selectedSubscriptionId.value = subscriptionId;
    editFormState.value = { ...initialFormState };
    isEditSubscriptionOpen.value = true;
  }

  function closeEditSubscription(): void {
    isEditSubscriptionOpen.value = false;
    selectedSubscriptionId.value = null;
    options.clearSaveError();
  }

  function openDeleteSubscription(subscriptionId: string): void {
    options.clearDeleteError();
    selectedSubscriptionId.value = subscriptionId;
    isDeleteSubscriptionOpen.value = true;
  }

  function closeDeleteSubscription(): void {
    isDeleteSubscriptionOpen.value = false;
    selectedSubscriptionId.value = null;
    options.clearDeleteError();
  }

  function handleCreateFormStateChange(state: FormState): void {
    createFormState.value = state;
  }

  function handleEditFormStateChange(state: FormState): void {
    editFormState.value = state;
  }

  return {
    closeCreateSubscription,
    closeDeleteSubscription,
    closeEditSubscription,
    closeFilters,
    createFormState,
    editFormState,
    handleCreateFormStateChange,
    handleEditFormStateChange,
    isCreateSubscriptionOpen,
    isDeleteSubscriptionOpen,
    isEditSubscriptionOpen,
    isFiltersOpen,
    openCreateSubscription,
    openDeleteSubscription,
    openEditSubscription,
    openFilters,
    selectedSubscription,
  };
}

export type { FormState as SubscriptionFormState };
