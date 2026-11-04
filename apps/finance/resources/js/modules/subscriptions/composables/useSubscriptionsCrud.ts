import { useInfiniteQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { computed, nextTick, ref } from 'vue';

import { resolveApiErrorMessage } from '@/modules/shared/lib/apiErrors';

import { subscriptionQueryKeys } from '../queries/subscriptionQueries';
import { createSubscriptionsRepository } from '../repositories/subscriptionsRepository';
import type { Subscription, SubscriptionListFilters, SubscriptionWritePayload } from '../types';

const subscriptionsRepository = createSubscriptionsRepository();

export function useSubscriptionsCrud() {
  const queryClient = useQueryClient();
  const perPage = ref(20);
  const lastFilters = ref<SubscriptionListFilters | undefined>();
  const loadError = ref<string | null>(null);
  const saveError = ref<string | null>(null);
  const deleteError = ref<string | null>(null);
  const isManualLoading = ref(false);
  const isManualLoadingMore = ref(false);

  const activeQueryKey = computed(() => subscriptionQueryKeys.list(lastFilters.value, perPage.value));

  const subscriptionsQuery = useInfiniteQuery({
    queryKey: activeQueryKey,
    enabled: false,
    initialPageParam: 1,
    queryFn: ({ pageParam }) =>
      subscriptionsRepository.list({
        page: pageParam,
        perPage: perPage.value,
        filters: lastFilters.value,
      }),
    getNextPageParam: (lastPage) =>
      lastPage.currentPage < lastPage.lastPage ? lastPage.currentPage + 1 : undefined,
  });

  const createSubscriptionMutation = useMutation({
    mutationFn: (payload: SubscriptionWritePayload) => subscriptionsRepository.create(payload),
  });
  const updateSubscriptionMutation = useMutation({
    mutationFn: ({
      subscriptionId,
      payload,
    }: {
      subscriptionId: string;
      payload: SubscriptionWritePayload;
    }) => subscriptionsRepository.update(subscriptionId, payload),
  });
  const deleteSubscriptionMutation = useMutation({
    mutationFn: (subscriptionId: string) => subscriptionsRepository.remove(subscriptionId),
  });

  const subscriptions = computed<Subscription[]>(
    () => subscriptionsQuery.data.value?.pages.flatMap((page) => page.items) ?? [],
  );
  const hasSubscriptions = computed(() => subscriptions.value.length > 0);
  const hasMoreSubscriptions = computed(() => subscriptionsQuery.hasNextPage.value);
  const isLoading = computed(() => isManualLoading.value || subscriptionsQuery.isLoading.value);
  const isLoadingMore = computed(
    () => isManualLoadingMore.value || subscriptionsQuery.isFetchingNextPage.value,
  );
  const hasReachedEnd = computed(
    () => hasSubscriptions.value && !hasMoreSubscriptions.value && !isLoadingMore.value,
  );
  const isSaving = computed(
    () => createSubscriptionMutation.isPending.value || updateSubscriptionMutation.isPending.value,
  );
  const isDeleting = computed(() => deleteSubscriptionMutation.isPending.value);

  async function loadSubscriptions(
    filters?: SubscriptionListFilters,
    options?: { reset?: boolean; perPage?: number },
  ): Promise<void> {
    const shouldReset = options?.reset ?? true;
    lastFilters.value = filters;
    perPage.value = options?.perPage ?? perPage.value;
    loadError.value = null;
    isManualLoading.value = shouldReset;
    isManualLoadingMore.value = !shouldReset;

    try {
      await nextTick();
      const result = await subscriptionsQuery.refetch();

      if (result.error) {
        loadError.value = resolveApiErrorMessage(
          result.error,
          'No fue posible cargar las suscripciones.',
        );
      }
    } catch (error) {
      loadError.value = resolveApiErrorMessage(error, 'No fue posible cargar las suscripciones.');
    } finally {
      isManualLoading.value = false;
      isManualLoadingMore.value = false;
    }
  }

  async function loadMoreSubscriptions(): Promise<void> {
    if (isLoading.value || isLoadingMore.value || !hasMoreSubscriptions.value) {
      return;
    }

    loadError.value = null;
    isManualLoadingMore.value = true;

    try {
      const result = await subscriptionsQuery.fetchNextPage();

      if (result.error) {
        loadError.value = resolveApiErrorMessage(
          result.error,
          'No fue posible cargar más suscripciones.',
        );
      }
    } catch (error) {
      loadError.value = resolveApiErrorMessage(error, 'No fue posible cargar más suscripciones.');
    } finally {
      isManualLoadingMore.value = false;
    }
  }

  async function createSubscription(payload: SubscriptionWritePayload): Promise<boolean> {
    saveError.value = null;

    try {
      await createSubscriptionMutation.mutateAsync(payload);
      await queryClient.invalidateQueries({ queryKey: subscriptionQueryKeys.all });
      await loadSubscriptions(lastFilters.value, { reset: true, perPage: perPage.value });
      return true;
    } catch (error) {
      saveError.value = resolveApiErrorMessage(error, 'No fue posible crear la suscripción.');
      return false;
    }
  }

  async function updateSubscription(
    subscriptionId: string,
    payload: SubscriptionWritePayload,
  ): Promise<boolean> {
    saveError.value = null;

    try {
      await updateSubscriptionMutation.mutateAsync({ subscriptionId, payload });
      await queryClient.invalidateQueries({ queryKey: subscriptionQueryKeys.all });
      await loadSubscriptions(lastFilters.value, { reset: true, perPage: perPage.value });
      return true;
    } catch (error) {
      saveError.value = resolveApiErrorMessage(error, 'No fue posible actualizar la suscripción.');
      return false;
    }
  }

  async function deleteSubscription(subscriptionId: string): Promise<boolean> {
    deleteError.value = null;

    try {
      await deleteSubscriptionMutation.mutateAsync(subscriptionId);
      await queryClient.invalidateQueries({ queryKey: subscriptionQueryKeys.all });
      await loadSubscriptions(lastFilters.value, { reset: true, perPage: perPage.value });
      return true;
    } catch (error) {
      deleteError.value = resolveApiErrorMessage(error, 'No fue posible eliminar la suscripción.');
      return false;
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
    hasMoreSubscriptions,
    hasReachedEnd,
    isLoading,
    isLoadingMore,
    isSaving,
    isDeleting,
    loadError,
    saveError,
    deleteError,
    clearSaveError,
    clearDeleteError,
    loadSubscriptions,
    loadMoreSubscriptions,
    createSubscription,
    updateSubscription,
    deleteSubscription,
    perPage,
  };
}
