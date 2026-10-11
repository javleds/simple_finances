import {
  readTransactionAdditionalFilters,
  additionalFiltersPayload,
  additionalFiltersQuery,
} from '@/modules/transactions/lib/transactionAdditionalFilters';
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { areQueriesEqual } from '@/modules/shared/lib/queryParams';
import {
  currentMonthRange,
  validateTransactionPeriod,
  type TransactionPeriod,
} from '@/modules/transactions/lib/transactionPeriod';
import type { TransactionFacilityFilters } from '@/modules/transactions/types';

export function useTransactionFacilityFilters() {
  const route = useRoute();
  const router = useRouter();
  const defaultRange = currentMonthRange();
  const searchTerm = ref('');
  const selectedCategories = ref<string[]>([]);
  const hasAttachments = ref<boolean | null>(null);
  const startDate = ref<string | null>(defaultRange.startDate);
  const endDate = ref<string | null>(defaultRange.endDate);

  const validationError = computed(() => validateTransactionPeriod(startDate.value, endDate.value));

  const activeFilters = computed<TransactionFacilityFilters | null>(() => {
    if (validationError.value || !startDate.value || !endDate.value) {
      return null;
    }

    return {
      search: searchTerm.value.trim() || undefined,
      ...additionalFiltersPayload(selectedCategories.value, hasAttachments.value),
      startDate: startDate.value,
      endDate: endDate.value,
    };
  });

  watch(
    () => route.query,
    (nextQuery) => {
      searchTerm.value = typeof nextQuery.search === 'string' ? nextQuery.search : '';
      const additional = readTransactionAdditionalFilters(nextQuery);
      if (selectedCategories.value.join(',') !== additional.categories.join(','))
        selectedCategories.value = additional.categories;
      hasAttachments.value = additional.hasAttachments;
      startDate.value =
        typeof nextQuery.start_date === 'string' ? nextQuery.start_date : defaultRange.startDate;
      endDate.value =
        typeof nextQuery.end_date === 'string' ? nextQuery.end_date : defaultRange.endDate;
    },
    { immediate: true },
  );

  watch([searchTerm, startDate, endDate, selectedCategories, hasAttachments], () => {
    const nextQuery = {
      ...route.query,
      ...additionalFiltersQuery(selectedCategories.value, hasAttachments.value),
      search: searchTerm.value.trim() || undefined,
      start_date: startDate.value || undefined,
      end_date: endDate.value || undefined,
    };

    if (areQueriesEqual(route.query, nextQuery)) {
      return;
    }

    void router.replace({ query: nextQuery });
  });

  function applyPeriod(period: TransactionPeriod): void {
    startDate.value = period.startDate;
    endDate.value = period.endDate;
  }

  function resetPeriod(): void {
    const range = currentMonthRange();
    startDate.value = range.startDate;
    endDate.value = range.endDate;
  }

  return {
    activeFilters,
    selectedCategories,
    hasAttachments,
    applyPeriod,
    endDate,
    resetPeriod,
    searchTerm,
    startDate,
    validationError,
  };
}
