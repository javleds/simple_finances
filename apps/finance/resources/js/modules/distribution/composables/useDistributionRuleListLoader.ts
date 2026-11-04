import { computed, watch, type ComputedRef, type Ref } from 'vue';
import { useRoute } from 'vue-router';

import type { DistributionRuleListFilters } from '@/modules/distribution/types';
import { useInfiniteScroll } from '@/modules/shared/composables/useInfiniteScroll';

type LoadRulesOptions = {
  reset?: boolean;
  perPage?: number;
};

type UseDistributionRuleListLoaderOptions = {
  activeFilters: ComputedRef<DistributionRuleListFilters>;
  hasMoreRules: ComputedRef<boolean>;
  hasReachedEnd: ComputedRef<boolean>;
  isLoading: Ref<boolean>;
  isLoadingMore: Ref<boolean>;
  loadMoreRules: () => Promise<void>;
  loadRules: (filters?: DistributionRuleListFilters, options?: LoadRulesOptions) => Promise<void>;
};

const defaultRulesPerPage = 20;

export function useDistributionRuleListLoader(options: UseDistributionRuleListLoaderOptions) {
  const route = useRoute();
  const rulesPerPage = computed(() => {
    const rawValue =
      typeof route.query.perPage === 'string' ? Number(route.query.perPage) : Number.NaN;

    if (!Number.isInteger(rawValue) || rawValue <= 0) {
      return defaultRulesPerPage;
    }

    return rawValue;
  });

  const { target: loadMoreSentinel } = useInfiniteScroll({
    enabled: computed(
      () => !options.isLoading.value && !options.isLoadingMore.value && options.hasMoreRules.value,
    ),
    onIntersect: () => {
      void options.loadMoreRules();
    },
  });

  watch(
    [options.activeFilters, rulesPerPage],
    ([nextFilters, nextPerPage]) => {
      void options.loadRules(nextFilters, {
        reset: true,
        perPage: nextPerPage,
      });
    },
    { immediate: true },
  );

  function reloadRules(): void {
    void options.loadRules(options.activeFilters.value, {
      reset: true,
      perPage: rulesPerPage.value,
    });
  }

  function handleLoadMoreRetry(): void {
    void options.loadMoreRules();
  }

  function infiniteStatusLabel(): string {
    if (options.isLoadingMore.value) {
      return 'Cargando más reglas...';
    }

    if (options.hasReachedEnd.value) {
      return 'Has llegado al final.';
    }

    return 'Sigue desplazándote para revisar más reglas conforme crezca la facility.';
  }

  return {
    handleLoadMoreRetry,
    infiniteStatusLabel,
    loadMoreSentinel,
    reloadRules,
  };
}
