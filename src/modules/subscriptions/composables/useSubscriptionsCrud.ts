import { computed, ref } from 'vue';

import { ApiError } from '@/lib/api/apiClient';

import { createSubscriptionsRepository } from '../repositories/subscriptionsRepository';
import type { Subscription, SubscriptionWritePayload } from '../types';

const subscriptionsRepository = createSubscriptionsRepository();

function resolveErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof ApiError) {
    return error.message;
  }

  if (error instanceof Error && error.message) {
    return error.message;
  }

  return fallback;
}

export function useSubscriptionsCrud() {
  const subscriptions = ref<Subscription[]>([]);
  const isLoading = ref(false);
  const isSaving = ref(false);
  const isDeleting = ref(false);
  const loadError = ref<string | null>(null);
  const saveError = ref<string | null>(null);
  const deleteError = ref<string | null>(null);

  const hasSubscriptions = computed(() => subscriptions.value.length > 0);

  async function loadSubscriptions(): Promise<void> {
    isLoading.value = true;
    loadError.value = null;

    try {
      subscriptions.value = await subscriptionsRepository.list();
    } catch (error) {
      loadError.value = resolveErrorMessage(error, 'No fue posible cargar las suscripciones.');
    } finally {
      isLoading.value = false;
    }
  }

  async function createSubscription(payload: SubscriptionWritePayload): Promise<boolean> {
    isSaving.value = true;
    saveError.value = null;

    try {
      const subscription = await subscriptionsRepository.create(payload);
      subscriptions.value = [subscription, ...subscriptions.value];
      return true;
    } catch (error) {
      saveError.value = resolveErrorMessage(error, 'No fue posible crear la suscripción.');
      return false;
    } finally {
      isSaving.value = false;
    }
  }

  async function updateSubscription(
    subscriptionId: string,
    payload: SubscriptionWritePayload,
  ): Promise<boolean> {
    isSaving.value = true;
    saveError.value = null;

    try {
      const updatedSubscription = await subscriptionsRepository.update(subscriptionId, payload);
      subscriptions.value = subscriptions.value.map((subscription) =>
        subscription.id === subscriptionId ? updatedSubscription : subscription,
      );
      return true;
    } catch (error) {
      saveError.value = resolveErrorMessage(error, 'No fue posible actualizar la suscripción.');
      return false;
    } finally {
      isSaving.value = false;
    }
  }

  async function deleteSubscription(subscriptionId: string): Promise<boolean> {
    isDeleting.value = true;
    deleteError.value = null;

    try {
      await subscriptionsRepository.remove(subscriptionId);
      subscriptions.value = subscriptions.value.filter(
        (subscription) => subscription.id !== subscriptionId,
      );
      return true;
    } catch (error) {
      deleteError.value = resolveErrorMessage(error, 'No fue posible eliminar la suscripción.');
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
    subscriptions,
    hasSubscriptions,
    isLoading,
    isSaving,
    isDeleting,
    loadError,
    saveError,
    deleteError,
    clearSaveError,
    clearDeleteError,
    loadSubscriptions,
    createSubscription,
    updateSubscription,
    deleteSubscription,
  };
}
