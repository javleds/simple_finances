import { useQuery } from '@tanstack/vue-query';
import { computed, nextTick, ref, watch } from 'vue';

import { resolveApiErrorMessage } from '@/modules/shared/lib/apiErrors';

import { dashboardQueryKeys } from '../queries/dashboardQueries';
import { createDashboardRepository } from '../repositories/dashboardRepository';

const dashboardRepository = createDashboardRepository();

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

export function useDashboardPeriodSummary() {
  const defaultRange = currentMonthRange();
  const startDate = ref<string | null>(defaultRange.startDate);
  const endDate = ref<string | null>(defaultRange.endDate);
  const loadError = ref<string | null>(null);
  const isManualLoading = ref(false);

  const validationError = computed(() => {
    if (!isDateValue(startDate.value) || !isDateValue(endDate.value)) {
      return 'Selecciona una fecha inicial y final.';
    }

    if (startDate.value > endDate.value) {
      return 'La fecha inicial debe ser anterior a la final.';
    }

    return null;
  });

  const periodParams = computed(() => {
    if (validationError.value || !isDateValue(startDate.value) || !isDateValue(endDate.value)) {
      return null;
    }

    return {
      startDate: startDate.value,
      endDate: endDate.value,
    };
  });

  const periodSummaryQuery = useQuery({
    queryKey: computed(() =>
      periodParams.value
        ? dashboardQueryKeys.periodSummary(periodParams.value)
        : [...dashboardQueryKeys.all, 'period-summary', 'invalid'],
    ),
    queryFn: () => {
      if (!periodParams.value) {
        throw new Error('A valid dashboard period is required.');
      }

      return dashboardRepository.loadPeriodSummary(periodParams.value);
    },
    enabled: false,
  });

  const summary = computed(() => periodSummaryQuery.data.value ?? null);
  const isLoading = computed(() => isManualLoading.value || periodSummaryQuery.isLoading.value);

  async function loadPeriodSummary(): Promise<void> {
    if (!periodParams.value) {
      return;
    }

    isManualLoading.value = true;
    loadError.value = null;

    try {
      await nextTick();
      const result = await periodSummaryQuery.refetch();

      if (result.error) {
        loadError.value = resolveApiErrorMessage(
          result.error,
          'No fue posible cargar el resumen del periodo.',
        );
      }
    } catch (error) {
      loadError.value = resolveApiErrorMessage(
        error,
        'No fue posible cargar el resumen del periodo.',
      );
    } finally {
      isManualLoading.value = false;
    }
  }

  function resetToCurrentMonth(): void {
    const range = currentMonthRange();
    startDate.value = range.startDate;
    endDate.value = range.endDate;
  }

  watch(
    [startDate, endDate],
    () => {
      if (validationError.value) {
        loadError.value = null;
        return;
      }

      void loadPeriodSummary();
    },
    { immediate: true },
  );

  return {
    startDate,
    endDate,
    summary,
    isLoading,
    loadError,
    validationError,
    resetToCurrentMonth,
    loadPeriodSummary,
  };
}
