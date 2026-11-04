import {
  readTransactionAdditionalFilters,
  additionalFiltersPayload,
  additionalFiltersQuery,
} from '@/modules/transactions/lib/transactionAdditionalFilters';
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import type { TransactionListFilters, TransactionType } from '@/modules/transactions/types';
import { areQueriesEqual, parseQueryValues } from '@/modules/shared/lib/queryParams';

export function useAccountTransactionFilters() {
  const route = useRoute();
  const router = useRouter();
  const searchTerm = ref('');
  const selectedCategories = ref<string[]>([]);
  const hasAttachments = ref<boolean | null>(null);
  const selectedTypes = ref<TransactionType[]>([]);

  const activeFilters = computed<TransactionListFilters>(() => ({
    search: searchTerm.value.trim() || undefined,
    ...additionalFiltersPayload(selectedCategories.value, hasAttachments.value),
    type: selectedTypes.value.length > 0 ? [...selectedTypes.value] : undefined,
  }));

  watch(
    () => route.query,
    (nextQuery) => {
      searchTerm.value = typeof nextQuery.search === 'string' ? nextQuery.search : '';
      const additional = readTransactionAdditionalFilters(nextQuery);
      if (selectedCategories.value.join(',') !== additional.categories.join(','))
        selectedCategories.value = additional.categories;
      hasAttachments.value = additional.hasAttachments;
      const types = parseQueryValues(nextQuery.type, isTransactionType);
      if (selectedTypes.value.join(',') !== types.join(',')) selectedTypes.value = types;
    },
    { immediate: true },
  );

  watch(
    [searchTerm, selectedTypes, selectedCategories, hasAttachments],
    () => {
      const nextQuery = {
        ...route.query,
        ...additionalFiltersQuery(selectedCategories.value, hasAttachments.value),
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
    selectedCategories.value = [];
    hasAttachments.value = null;
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
    selectedCategories,
    hasAttachments,
    clearFilters,
    searchTerm,
    selectedTypes,
    toggleType,
  };
}

function isTransactionType(value: string): value is TransactionType {
  return value === 'income' || value === 'expense';
}
