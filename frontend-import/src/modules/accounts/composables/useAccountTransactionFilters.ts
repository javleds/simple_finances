import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import type { TransactionListFilters, TransactionType } from '@/modules/transactions/types';
import { areQueriesEqual, parseQueryValues } from '@/modules/shared/lib/queryParams';

export function useAccountTransactionFilters() {
  const route = useRoute();
  const router = useRouter();
  const searchTerm = ref('');
  const selectedTypes = ref<TransactionType[]>([]);

  const activeFilters = computed<TransactionListFilters>(() => ({
    search: searchTerm.value.trim() || undefined,
    type: selectedTypes.value.length > 0 ? [...selectedTypes.value] : undefined,
  }));

  watch(
    () => route.query,
    (nextQuery) => {
      searchTerm.value = typeof nextQuery.search === 'string' ? nextQuery.search : '';
      selectedTypes.value = parseQueryValues(nextQuery.type, isTransactionType);
    },
    { immediate: true },
  );

  watch(
    [searchTerm, selectedTypes],
    () => {
      const nextQuery = {
        ...route.query,
        search: searchTerm.value.trim() || undefined,
        status: undefined,
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
    selectedTypes.value = [];
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
    selectedTypes,
    toggleType,
  };
}

function isTransactionType(value: string): value is TransactionType {
  return value === 'income' || value === 'expense';
}
