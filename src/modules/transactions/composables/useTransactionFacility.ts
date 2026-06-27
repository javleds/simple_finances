import { computed, ref, shallowRef } from 'vue';

import { ApiError } from '@/lib/api/apiClient';
import type {
  Transaction,
  TransactionFacilityFilters,
  TransactionFacilitySummary,
} from '@/modules/transactions/types';

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
  const transactions = shallowRef<Transaction[]>([]);
  const summary = ref<TransactionFacilitySummary>(defaultSummary);
  const isLoading = ref(false);
  const isLoadingMore = ref(false);
  const loadError = ref<string | null>(null);
  const currentPage = ref(1);
  const lastPage = ref(1);
  const perPage = ref(20);
  const lastFilters = ref<TransactionFacilityFilters | null>(null);

  const hasTransactions = computed(() => transactions.value.length > 0);
  const hasMoreTransactions = computed(() => currentPage.value < lastPage.value);
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

    if (shouldReset) {
      isLoading.value = true;
    } else {
      isLoadingMore.value = true;
    }

    try {
      const response = await transactionFacilityRepository.list({
        page: 1,
        perPage: nextPerPage,
        filters,
      });

      transactions.value = response.items;
      summary.value = response.summary;
      currentPage.value = response.currentPage;
      lastPage.value = response.lastPage;
    } catch (error) {
      loadError.value = resolveErrorMessage(error, 'No fue posible cargar las transacciones.');
    } finally {
      if (shouldReset) {
        isLoading.value = false;
      } else {
        isLoadingMore.value = false;
      }
    }
  }

  async function loadMoreTransactions(): Promise<void> {
    if (isLoading.value || isLoadingMore.value || !hasMoreTransactions.value || !lastFilters.value) {
      return;
    }

    isLoadingMore.value = true;
    loadError.value = null;

    try {
      const response = await transactionFacilityRepository.list({
        page: currentPage.value + 1,
        perPage: perPage.value,
        filters: lastFilters.value,
      });

      transactions.value = [...transactions.value, ...response.items];
      summary.value = response.summary;
      currentPage.value = response.currentPage;
      lastPage.value = response.lastPage;
    } catch (error) {
      loadError.value = resolveErrorMessage(error, 'No fue posible cargar más transacciones.');
    } finally {
      isLoadingMore.value = false;
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
