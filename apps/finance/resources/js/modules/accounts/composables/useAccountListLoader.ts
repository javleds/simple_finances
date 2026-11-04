import { computed, watch, type ComputedRef, type Ref } from 'vue';
import { useRoute } from 'vue-router';

import type { AccountListFilters } from '@/modules/accounts/types';
import { useInfiniteScroll } from '@/modules/shared/composables/useInfiniteScroll';

type LoadAccountsOptions = {
  reset?: boolean;
  perPage?: number;
};

type UseAccountListLoaderOptions = {
  activeFilters: ComputedRef<AccountListFilters>;
  hasMoreAccounts: ComputedRef<boolean>;
  hasReachedEnd: ComputedRef<boolean>;
  isLoading: Ref<boolean>;
  isLoadingMore: Ref<boolean>;
  loadAccounts: (filters?: AccountListFilters, options?: LoadAccountsOptions) => Promise<void>;
  loadMoreAccounts: () => Promise<void>;
};

const defaultAccountsPerPage = 20;

export function useAccountListLoader(options: UseAccountListLoaderOptions) {
  const route = useRoute();
  const accountsPerPage = computed(() => {
    const rawValue =
      typeof route.query.perPage === 'string' ? Number(route.query.perPage) : Number.NaN;

    if (!Number.isInteger(rawValue) || rawValue <= 0) {
      return defaultAccountsPerPage;
    }

    return rawValue;
  });

  const { target: loadMoreSentinel } = useInfiniteScroll({
    enabled: computed(
      () =>
        !options.isLoading.value && !options.isLoadingMore.value && options.hasMoreAccounts.value,
    ),
    onIntersect: () => {
      void options.loadMoreAccounts();
    },
  });

  watch(
    [options.activeFilters, accountsPerPage],
    ([nextFilters, nextPerPage]) => {
      void options.loadAccounts(nextFilters, {
        reset: true,
        perPage: nextPerPage,
      });
    },
    { immediate: true },
  );

  function reloadAccounts(): void {
    void options.loadAccounts(options.activeFilters.value, {
      reset: true,
      perPage: accountsPerPage.value,
    });
  }

  function handleLoadMoreRetry(): void {
    void options.loadMoreAccounts();
  }

  function infiniteStatusLabel(): string {
    if (options.isLoadingMore.value) {
      return 'Cargando más cuentas...';
    }

    if (options.hasReachedEnd.value) {
      return 'Has llegado al final.';
    }

    return 'Sigue desplazándote para explorar más cuentas cuando la facility crezca.';
  }

  return {
    handleLoadMoreRetry,
    infiniteStatusLabel,
    loadMoreSentinel,
    reloadAccounts,
  };
}
