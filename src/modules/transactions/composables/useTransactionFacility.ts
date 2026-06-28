import { useInfiniteQuery } from '@tanstack/vue-query';
import { computed, nextTick, ref } from 'vue';

import { ApiError } from '@/lib/api/apiClient';
import type {
  Transaction,
  TransactionFacilityFilters,
  TransactionFacilitySummary,
} from '@/modules/transactions/types';
import { transactionFacilityQueryKeys } from '@/modules/transactions/queries/transactionFacilityQueries';

import { createTransactionFacilityRepository } from '../repositories/transactionFacilityRepository';

const transactionFacilityRepository = createTransactionFacilityRepository();
const defaultSummary: TransactionFacilitySummary = {
  incomeTotal: 0,
  outcomeTotal: 0,
  balance: 0,
};

function resolveErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof ApiError) {
    return error.message;
  }

  if (error instanceof Error && error.message) {
    return error.message;
  }

  return fallback;
}

export function useTransactionFacility() {
  const perPage = ref(20);
  const lastFilters = ref<TransactionFacilityFilters | null>(null);
  const loadError = ref<string | null>(null);
  const isManualLoading = ref(false);
  const isManualLoadingMore = ref(false);

  const query = useInfiniteQuery({
    queryKey: computed(() =>
      lastFilters.value
        ? transactionFacilityQueryKeys.list(lastFilters.value, perPage.value)
        : transactionFacilityQueryKeys.all,
    ),
    enabled: false,
    initialPageParam: 1,
    queryFn: ({ pageParam }) => {
      if (!lastFilters.value) {
        throw new Error('Transaction facility filters are required.');
      }

      return transactionFacilityRepository.list({
        page: pageParam,
        perPage: perPage.value,
        filters: lastFilters.value,
      });
    },
    getNextPageParam: (lastPage) =>
      lastPage.currentPage < lastPage.lastPage ? lastPage.currentPage + 1 : undefined,
  });

  const transactions = computed<Transaction[]>(
    () => query.data.value?.pages.flatMap((page) => page.items) ?? [],
  );
  const summary = computed<TransactionFacilitySummary>(
    () => query.data.value?.pages[0]?.summary ?? defaultSummary,
  );
  const hasTransactions = computed(() => transactions.value.length > 0);
  const hasMoreTransactions = computed(() => query.hasNextPage.value);
  const isLoading = computed(() => isManualLoading.value || query.isLoading.value);
  const isLoadingMore = computed(
    () => isManualLoadingMore.value || query.isFetchingNextPage.value,
  );
  const hasReachedEnd = computed(
    () => hasTransactions.value && !hasMoreTransactions.value && !isLoadingMore.value,
  );

  async function loadTransactions(
    filters: TransactionFacilityFilters,
    options?: { reset?: boolean; perPage?: number },
  ): Promise<void> {
    const shouldReset = options?.reset ?? true;
    const nextPerPage = options?.perPage ?? perPage.value;

    lastFilters.value = filters;
    perPage.value = nextPerPage;
    loadError.value = null;

    isManualLoading.value = shouldReset;
    isManualLoadingMore.value = !shouldReset;

    try {
      await nextTick();
      const result = await query.refetch();

      if (result.error) {
        loadError.value = resolveErrorMessage(
          result.error,
          'No fue posible cargar las transacciones.',
        );
      }
    } catch (error) {
      loadError.value = resolveErrorMessage(error, 'No fue posible cargar las transacciones.');
    } finally {
      isManualLoading.value = false;
      isManualLoadingMore.value = false;
    }
  }

  async function loadMoreTransactions(): Promise<void> {
    if (isLoading.value || isLoadingMore.value || !hasMoreTransactions.value || !lastFilters.value) {
      return;
    }

    isManualLoadingMore.value = true;
    loadError.value = null;

    try {
      const result = await query.fetchNextPage();

      if (result.error) {
        loadError.value = resolveErrorMessage(
          result.error,
          'No fue posible cargar más transacciones.',
        );
      }
    } catch (error) {
      loadError.value = resolveErrorMessage(error, 'No fue posible cargar más transacciones.');
    } finally {
      isManualLoadingMore.value = false;
    }
  }

  return {
    transactions,
    summary,
    hasTransactions,
    hasMoreTransactions,
    hasReachedEnd,
    isLoading,
    isLoadingMore,
    loadError,
    loadTransactions,
    loadMoreTransactions,
  };
}
