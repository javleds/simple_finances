import { computed, watch, type ComputedRef, type Ref } from 'vue';
import { useRoute } from 'vue-router';

import type { SubscriptionListFilters } from '@/modules/subscriptions/types';
import { useInfiniteScroll } from '@/modules/shared/composables/useInfiniteScroll';

type LoadSubscriptionsOptions = {
  reset?: boolean;
  perPage?: number;
};

type UseSubscriptionListLoaderOptions = {
  activeFilters: ComputedRef<SubscriptionListFilters>;
  hasMoreSubscriptions: ComputedRef<boolean>;
  hasReachedEnd: ComputedRef<boolean>;
  isLoading: Ref<boolean>;
  isLoadingMore: Ref<boolean>;
  loadMoreSubscriptions: () => Promise<void>;
  loadSubscriptions: (
    filters?: SubscriptionListFilters,
    options?: LoadSubscriptionsOptions,
  ) => Promise<void>;
};

const defaultSubscriptionsPerPage = 20;

export function useSubscriptionListLoader(options: UseSubscriptionListLoaderOptions) {
  const route = useRoute();
  const subscriptionsPerPage = computed(() => {
    const rawValue =
      typeof route.query.perPage === 'string' ? Number(route.query.perPage) : Number.NaN;

    if (!Number.isInteger(rawValue) || rawValue <= 0) {
      return defaultSubscriptionsPerPage;
    }

    return rawValue;
  });

  const { target: loadMoreSentinel } = useInfiniteScroll({
    enabled: computed(
      () =>
        !options.isLoading.value &&
        !options.isLoadingMore.value &&
        options.hasMoreSubscriptions.value,
    ),
    onIntersect: () => {
      void options.loadMoreSubscriptions();
    },
  });

  watch(
    [options.activeFilters, subscriptionsPerPage],
    ([nextFilters, nextPerPage]) => {
      void options.loadSubscriptions(nextFilters, {
        reset: true,
        perPage: nextPerPage,
      });
    },
    { immediate: true },
  );

  function reloadSubscriptions(): void {
    void options.loadSubscriptions(options.activeFilters.value, {
      reset: true,
      perPage: subscriptionsPerPage.value,
    });
  }

  function handleLoadMoreRetry(): void {
    void options.loadMoreSubscriptions();
  }

  function infiniteStatusLabel(): string {
    if (options.isLoadingMore.value) {
      return 'Cargando más suscripciones...';
    }

    if (options.hasReachedEnd.value) {
      return 'Has llegado al final.';
    }

    return 'Sigue desplazándote para revisar más planes y complementos conforme crezca la cobertura contratada.';
  }

  return {
    handleLoadMoreRetry,
    infiniteStatusLabel,
    loadMoreSentinel,
    reloadSubscriptions,
  };
}
