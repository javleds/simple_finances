import { computed, ref, watch } from 'vue';

import { ApiError } from '@/lib/api/apiClient';

import { createDashboardRepository } from '../repositories/dashboardRepository';
import type { DashboardPeriodSummary } from '../types/dashboard';

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

function resolveErrorMessage(error: unknown): string {
  if (error instanceof ApiError) {
    return error.message;
  }

  if (error instanceof Error && error.message) {
    return error.message;
  }

  return 'No fue posible cargar el resumen del periodo.';
}

export function useDashboardPeriodSummary() {
  const defaultRange = currentMonthRange();
  const startDate = ref<string | null>(defaultRange.startDate);
  const endDate = ref<string | null>(defaultRange.endDate);
  const summary = ref<DashboardPeriodSummary | null>(null);
  const isLoading = ref(false);
  const loadError = ref<string | null>(null);
  let requestVersion = 0;

  const validationError = computed(() => {
    if (!isDateValue(startDate.value) || !isDateValue(endDate.value)) {
      return 'Selecciona una fecha inicial y final.';
    }

    if (startDate.value > endDate.value) {
      return 'La fecha inicial debe ser anterior a la final.';
    }

    return null;
  });

  async function loadPeriodSummary(): Promise<void> {
    if (validationError.value || !isDateValue(startDate.value) || !isDateValue(endDate.value)) {
      return;
    }

    const currentRequestVersion = requestVersion + 1;
    requestVersion = currentRequestVersion;
    isLoading.value = true;
    loadError.value = null;

    try {
      const result = await dashboardRepository.loadPeriodSummary({
        startDate: startDate.value,
        endDate: endDate.value,
      });

      if (currentRequestVersion !== requestVersion) {
        return;
      }

      summary.value = result;
    } catch (error) {
      if (currentRequestVersion !== requestVersion) {
        return;
      }

      loadError.value = resolveErrorMessage(error);
    } finally {
      if (currentRequestVersion === requestVersion) {
        isLoading.value = false;
      }
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
