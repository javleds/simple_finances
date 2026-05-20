import { computed, ref } from 'vue';

import { ApiError } from '@/lib/api/apiClient';
import { usePaginatedCollection } from '@/modules/shared/composables/usePaginatedCollection';

import { createSubscriptionsRepository } from '../repositories/subscriptionsRepository';
import type { Subscription, SubscriptionListFilters, SubscriptionWritePayload } from '../types';

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
  const subscriptionsState = usePaginatedCollection<Subscription, [SubscriptionListFilters | undefined]>({
    defaultPerPage: 20,
    loadPage: (options, filters) => subscriptionsRepository.list({ ...options, filters }),
    resolveErrorMessage,
    loadErrorMessage: 'No fue posible cargar las suscripciones.',
    loadMoreErrorMessage: 'No fue posible cargar más suscripciones.',
  });
  const isSaving = ref(false);
  const isDeleting = ref(false);
  const saveError = ref<string | null>(null);
  const deleteError = ref<string | null>(null);

  const hasSubscriptions = computed(() => subscriptionsState.hasItems.value);
  const hasMoreSubscriptions = computed(() => subscriptionsState.hasMoreItems.value);
  const hasReachedEnd = computed(() => subscriptionsState.hasReachedEnd.value);

  async function loadSubscriptions(
    filters?: SubscriptionListFilters,
    options?: { reset?: boolean; perPage?: number },
  ): Promise<void> {
    await subscriptionsState.load([filters], options);
  }

  async function loadMoreSubscriptions(): Promise<void> {
    await subscriptionsState.loadMore();
  }

  async function createSubscription(payload: SubscriptionWritePayload): Promise<boolean> {
    isSaving.value = true;
    saveError.value = null;

    try {
      const subscription = await subscriptionsRepository.create(payload);
      subscriptionsState.prependItem(subscription);
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
      subscriptionsState.replaceItem(
        (subscription) => subscription.id === subscriptionId,
        updatedSubscription,
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
      subscriptionsState.removeItem((subscription) => subscription.id === subscriptionId);
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
    subscriptions: subscriptionsState.items,
    hasSubscriptions,
    hasMoreSubscriptions,
    hasReachedEnd,
    isLoading: subscriptionsState.isLoading,
    isLoadingMore: subscriptionsState.isLoadingMore,
    isSaving,
    isDeleting,
    loadError: subscriptionsState.loadError,
    saveError,
    deleteError,
    clearSaveError,
    clearDeleteError,
    loadSubscriptions,
    loadMoreSubscriptions,
    createSubscription,
    updateSubscription,
    deleteSubscription,
    perPage: subscriptionsState.perPage,
  };
}
