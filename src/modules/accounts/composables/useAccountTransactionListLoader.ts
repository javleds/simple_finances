import { computed, watch, type ComputedRef, type Ref } from 'vue';
import { useRoute } from 'vue-router';

import { useInfiniteScroll } from '@/modules/shared/composables/useInfiniteScroll';
import type { TransactionListFilters } from '@/modules/transactions/types';

type LoadTransactionsOptions = {
  reset?: boolean;
  perPage?: number;
};

type UseAccountTransactionListLoaderOptions = {
  accountId: ComputedRef<string>;
  activeFilters: ComputedRef<TransactionListFilters>;
  hasMoreTransactions: ComputedRef<boolean>;
  hasReachedEnd: ComputedRef<boolean>;
  isLoading: Ref<boolean>;
  isLoadingMore: Ref<boolean>;
  loadMoreTransactions: () => Promise<void>;
  loadTransactions: (
    accountId: string,
    filters?: TransactionListFilters,
    options?: LoadTransactionsOptions,
  ) => Promise<void>;
};

const defaultTransactionsPerPage = 20;

export function useAccountTransactionListLoader(options: UseAccountTransactionListLoaderOptions) {
  const route = useRoute();
  const transactionsPerPage = computed(() => {
    const rawValue =
      typeof route.query.perPage === 'string' ? Number(route.query.perPage) : Number.NaN;

    if (!Number.isInteger(rawValue) || rawValue <= 0) {
      return defaultTransactionsPerPage;
    }

    return rawValue;
  });

  const { target: loadMoreSentinel } = useInfiniteScroll({
    enabled: computed(
      () =>
        !options.isLoading.value &&
        !options.isLoadingMore.value &&
        options.hasMoreTransactions.value,
    ),
    onIntersect: () => {
      void options.loadMoreTransactions();
    },
  });

  watch(
    [options.accountId, options.activeFilters, transactionsPerPage],
    ([nextAccountId, nextFilters, nextPerPage]) => {
      if (!nextAccountId) {
        return;
      }

      void options.loadTransactions(nextAccountId, nextFilters, {
        reset: true,
        perPage: nextPerPage,
      });
    },
    { immediate: true },
  );

  async function reloadTransactions(): Promise<void> {
    if (!options.accountId.value) {
      return;
    }

    await options.loadTransactions(options.accountId.value, options.activeFilters.value, {
      reset: true,
      perPage: transactionsPerPage.value,
    });
  }

  function handleLoadMoreRetry(): void {
    void options.loadMoreTransactions();
  }

  function infiniteStatusLabel(): string {
    if (options.isLoadingMore.value) {
      return 'Cargando más transacciones...';
    }

    if (options.hasReachedEnd.value) {
      return 'Has llegado al final.';
    }

    return 'Sigue desplazándote para revisar más actividad conforme la cuenta acumule movimientos.';
  }

  return {
    handleLoadMoreRetry,
    infiniteStatusLabel,
    loadMoreSentinel,
    reloadTransactions,
    transactionsPerPage,
  };
}
