import { computed, watch, type ComputedRef, type Ref } from 'vue';
import { useRoute } from 'vue-router';

import { useInfiniteScroll } from '@/modules/shared/composables/useInfiniteScroll';

type LoadRuleOptions = {
  reset?: boolean;
  perPage?: number;
};

type UseDistributionRelationListLoaderOptions = {
  hasMoreRelations: ComputedRef<boolean>;
  hasReachedEnd: ComputedRef<boolean>;
  isLoading: Ref<boolean>;
  isLoadingMore: Ref<boolean>;
  loadMoreRelations: () => Promise<void>;
  loadRule: (ruleId: string, options?: LoadRuleOptions) => Promise<void>;
  ruleId: ComputedRef<string>;
};

const defaultRelationsPerPage = 20;

export function useDistributionRelationListLoader(
  options: UseDistributionRelationListLoaderOptions,
) {
  const route = useRoute();
  const relationsPerPage = computed(() => {
    const rawValue =
      typeof route.query.perPage === 'string' ? Number(route.query.perPage) : Number.NaN;

    if (!Number.isInteger(rawValue) || rawValue <= 0) {
      return defaultRelationsPerPage;
    }

    return rawValue;
  });

  const { target: loadMoreSentinel } = useInfiniteScroll({
    enabled: computed(
      () =>
        !options.isLoading.value && !options.isLoadingMore.value && options.hasMoreRelations.value,
    ),
    onIntersect: () => {
      void options.loadMoreRelations();
    },
  });

  watch(
    [options.ruleId, relationsPerPage],
    ([nextRuleId, nextPerPage]) => {
      if (!nextRuleId) {
        return;
      }

      void options.loadRule(nextRuleId, {
        reset: true,
        perPage: nextPerPage,
      });
    },
    { immediate: true },
  );

  function reloadRelations(): void {
    if (!options.ruleId.value) {
      return;
    }

    void options.loadRule(options.ruleId.value, {
      reset: true,
      perPage: relationsPerPage.value,
    });
  }

  function handleLoadMoreRetry(): void {
    void options.loadMoreRelations();
  }

  function infiniteStatusLabel(): string {
    if (options.isLoadingMore.value) {
      return 'Cargando más relaciones...';
    }

    if (options.hasReachedEnd.value) {
      return 'Has llegado al final.';
    }

    return 'Sigue desplazándote para revisar más relaciones conforme crezca la regla.';
  }

  return {
    handleLoadMoreRetry,
    infiniteStatusLabel,
    loadMoreSentinel,
    reloadRelations,
  };
}
