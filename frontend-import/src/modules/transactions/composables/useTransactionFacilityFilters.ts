import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { areQueriesEqual } from '@/modules/shared/lib/queryParams';
import type { TransactionFacilityFilters } from '@/modules/transactions/types';

function formatDate(date: Date): string {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');

  return `${year}-${month}-${day}`;
}

function currentMonthRange(): { startDate: string; endDate: string } {
  const today = new Date();
  const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
  const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);

  return {
    startDate: formatDate(firstDay),
    endDate: formatDate(lastDay),
  };
}

function isDateValue(value: string | null): value is string {
  return typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value);
}

export function useTransactionFacilityFilters() {
  const route = useRoute();
  const router = useRouter();
  const defaultRange = currentMonthRange();
  const searchTerm = ref('');
  const startDate = ref<string | null>(defaultRange.startDate);
  const endDate = ref<string | null>(defaultRange.endDate);

  const validationError = computed(() => {
    if (!isDateValue(startDate.value) || !isDateValue(endDate.value)) {
      return 'Selecciona una fecha inicial y final.';
    }

    if (startDate.value > endDate.value) {
      return 'La fecha inicial debe ser anterior a la final.';
    }

    return null;
  });

  const activeFilters = computed<TransactionFacilityFilters | null>(() => {
    if (validationError.value || !isDateValue(startDate.value) || !isDateValue(endDate.value)) {
      return null;
    }

    return {
      search: searchTerm.value.trim() || undefined,
      startDate: startDate.value,
      endDate: endDate.value,
    };
  });

  watch(
    () => route.query,
    (nextQuery) => {
      searchTerm.value = typeof nextQuery.search === 'string' ? nextQuery.search : '';
      startDate.value =
        typeof nextQuery.start_date === 'string' ? nextQuery.start_date : defaultRange.startDate;
      endDate.value =
        typeof nextQuery.end_date === 'string' ? nextQuery.end_date : defaultRange.endDate;
    },
    { immediate: true },
  );

  watch([searchTerm, startDate, endDate], () => {
    const nextQuery = {
      ...route.query,
      search: searchTerm.value.trim() || undefined,
      start_date: startDate.value || undefined,
      end_date: endDate.value || undefined,
    };

    if (areQueriesEqual(route.query, nextQuery)) {
      return;
    }

    void router.replace({ query: nextQuery });
  });

  function resetPeriod(): void {
    const range = currentMonthRange();
    startDate.value = range.startDate;
    endDate.value = range.endDate;
  }

  return {
    activeFilters,
    endDate,
    resetPeriod,
    searchTerm,
    startDate,
    validationError,
  };
}
