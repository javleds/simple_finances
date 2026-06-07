import { computed, ref } from 'vue';

import { ApiError } from '@/lib/api/apiClient';

import { createDashboardRepository } from '../repositories/dashboardRepository';
import type { DashboardData } from '../types/dashboard';

const dashboardRepository = createDashboardRepository();

const emptyDashboardData: DashboardData = {
  graphAccounts: [],
  accountsSummary: {
    activeAccounts: 0,
    sharedAccounts: 0,
    pendingTotal: 0,
  },
  pendingActions: [],
  subscriptionsSummary: {
    annualTotal: 0,
    subscriptionsCount: 0,
  },
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

export function useDashboard() {
  const dashboard = ref<DashboardData>(emptyDashboardData);
  const isLoading = ref(false);
  const isCompletingPendingActions = ref(false);
  const loadError = ref<string | null>(null);
  const completeError = ref<string | null>(null);

  const hasDashboardData = computed(
    () =>
      dashboard.value.graphAccounts.length > 0 ||
      dashboard.value.pendingActions.length > 0 ||
      dashboard.value.accountsSummary.activeAccounts > 0 ||
      dashboard.value.accountsSummary.sharedAccounts > 0 ||
      dashboard.value.subscriptionsSummary.subscriptionsCount > 0,
  );

  async function loadDashboard(): Promise<void> {
    isLoading.value = true;
    loadError.value = null;

    try {
      dashboard.value = await dashboardRepository.load();
    } catch (error) {
      loadError.value = resolveErrorMessage(error, 'No fue posible cargar el dashboard.');
    } finally {
      isLoading.value = false;
    }
  }

  async function completePendingTransactions(transactionIds: string[]): Promise<boolean> {
    if (transactionIds.length === 0) {
      return false;
    }

    isCompletingPendingActions.value = true;
    completeError.value = null;

    try {
      const result = await dashboardRepository.completePendingTransactions(transactionIds);
      const completedIds =
        result.transactionIds.length > 0 ? result.transactionIds : transactionIds;
      const failedIds = new Set(result.failed.map((item) => item.id));
      const removableIds = new Set(
        completedIds.filter((transactionId) => !failedIds.has(transactionId)),
      );

      dashboard.value = {
        ...dashboard.value,
        pendingActions: dashboard.value.pendingActions.filter(
          (action) => !removableIds.has(action.id),
        ),
      };

      if (result.failed.length > 0) {
        completeError.value = result.failed.map((item) => item.message).join(' ');
        return false;
      }

      await loadDashboard();
      return true;
    } catch (error) {
      completeError.value = resolveErrorMessage(error, 'No fue posible completar los movimientos.');
      return false;
    } finally {
      isCompletingPendingActions.value = false;
    }
  }

  function clearCompleteError(): void {
    completeError.value = null;
  }

  return {
    dashboard,
    hasDashboardData,
    isLoading,
    isCompletingPendingActions,
    loadError,
    completeError,
    clearCompleteError,
    loadDashboard,
    completePendingTransactions,
  };
}
