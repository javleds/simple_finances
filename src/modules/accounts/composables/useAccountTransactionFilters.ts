import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import type {
  TransactionListFilters,
  TransactionStatus,
  TransactionType,
} from '@/modules/transactions/types';
import { areQueriesEqual, parseQueryValues } from '@/modules/shared/lib/queryParams';

export function useAccountTransactionFilters() {
  const route = useRoute();
  const router = useRouter();
  const searchTerm = ref('');
  const selectedStatuses = ref<TransactionStatus[]>([]);
  const selectedTypes = ref<TransactionType[]>([]);

  const activeFilters = computed<TransactionListFilters>(() => ({
    search: searchTerm.value.trim() || undefined,
    status: selectedStatuses.value.length > 0 ? [...selectedStatuses.value] : undefined,
    type: selectedTypes.value.length > 0 ? [...selectedTypes.value] : undefined,
  }));

  watch(
    () => route.query,
    (nextQuery) => {
      searchTerm.value = typeof nextQuery.search === 'string' ? nextQuery.search : '';
      selectedStatuses.value = parseQueryValues(nextQuery.status, isTransactionStatus);
      selectedTypes.value = parseQueryValues(nextQuery.type, isTransactionType);
    },
    { immediate: true },
  );

  watch(
    [searchTerm, selectedStatuses, selectedTypes],
    () => {
      const nextQuery = {
        ...route.query,
        search: searchTerm.value.trim() || undefined,
        status: selectedStatuses.value.length > 0 ? selectedStatuses.value.join(',') : undefined,
        type: selectedTypes.value.length > 0 ? selectedTypes.value.join(',') : undefined,
      };

      if (areQueriesEqual(route.query, nextQuery)) {
        return;
      }

      void router.replace({ query: nextQuery });
    },
    { deep: true },
  );

  function clearFilters(): void {
    selectedStatuses.value = [];
    selectedTypes.value = [];
  }

  function toggleStatus(status: TransactionStatus): void {
    if (selectedStatuses.value.includes(status)) {
      selectedStatuses.value = selectedStatuses.value.filter((item) => item !== status);
      return;
    }

    selectedStatuses.value = [...selectedStatuses.value, status];
  }

  function toggleType(type: TransactionType): void {
    if (selectedTypes.value.includes(type)) {
      selectedTypes.value = selectedTypes.value.filter((item) => item !== type);
      return;
    }

    selectedTypes.value = [...selectedTypes.value, type];
  }

  return {
    activeFilters,
    clearFilters,
    searchTerm,
    selectedStatuses,
    selectedTypes,
    toggleStatus,
    toggleType,
  };
}

function isTransactionStatus(value: string): value is TransactionStatus {
  return value === 'completed' || value === 'pending';
}

function isTransactionType(value: string): value is TransactionType {
  return value === 'income' || value === 'expense';
}
