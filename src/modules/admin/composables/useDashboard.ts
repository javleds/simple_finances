import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import { computed, ref } from 'vue';

import { resolveApiErrorMessage } from '@/modules/shared/lib/apiErrors';

import { dashboardQueryKeys } from '../queries/dashboardQueries';
import { createDashboardRepository } from '../repositories/dashboardRepository';
import type { DashboardData } from '../types/dashboard';

const dashboardRepository = createDashboardRepository();

const emptyDashboardData: DashboardData = {
  graphAccounts: [],
  accountsSummary: {
    activeAccounts: 0,
    sharedAccounts: 0,
    virtualAccounts: 0,
    pendingTotal: 0,
  },
  pendingActions: [],
  subscriptionsSummary: {
    annualTotal: 0,
    nearestPayment: null,
    savingsTargetToday: 0,
    subscriptionsCount: 0,
    upcomingCommitment: 0,
  },
};

export function useDashboard() {
  const queryClient = useQueryClient();
  const loadError = ref<string | null>(null);
  const completeError = ref<string | null>(null);
  const isManualLoading = ref(false);

  const dashboardQuery = useQuery({
    queryKey: dashboardQueryKeys.data(),
    queryFn: () => dashboardRepository.load(),
    enabled: false,
  });

  const completePendingTransactionsMutation = useMutation({
    mutationFn: (transactionIds: string[]) =>
      dashboardRepository.completePendingTransactions(transactionIds),
  });

  const dashboard = computed<DashboardData>(() => dashboardQuery.data.value ?? emptyDashboardData);
  const isLoading = computed(() => isManualLoading.value || dashboardQuery.isLoading.value);
  const isCompletingPendingActions = computed(
    () => completePendingTransactionsMutation.isPending.value,
  );

  const hasDashboardData = computed(
    () =>
      dashboard.value.graphAccounts.length > 0 ||
      dashboard.value.pendingActions.length > 0 ||
      dashboard.value.accountsSummary.activeAccounts > 0 ||
      dashboard.value.accountsSummary.sharedAccounts > 0 ||
      dashboard.value.accountsSummary.virtualAccounts > 0 ||
      dashboard.value.subscriptionsSummary.subscriptionsCount > 0,
  );

  async function loadDashboard(): Promise<void> {
    isManualLoading.value = true;
    loadError.value = null;

    try {
      const result = await dashboardQuery.refetch();

      if (result.error) {
        loadError.value = resolveApiErrorMessage(
          result.error,
          'No fue posible cargar el dashboard.',
        );
      }
    } catch (error) {
      loadError.value = resolveApiErrorMessage(error, 'No fue posible cargar el dashboard.');
    } finally {
      isManualLoading.value = false;
    }
  }

  async function completePendingTransactions(transactionIds: string[]): Promise<boolean> {
    if (transactionIds.length === 0) {
      return false;
    }

    completeError.value = null;

    try {
      const result = await completePendingTransactionsMutation.mutateAsync(transactionIds);
      const completedIds =
        result.transactionIds.length > 0 ? result.transactionIds : transactionIds;
      const failedIds = new Set(result.failed.map((item) => item.id));
      const removableIds = new Set(
        completedIds.filter((transactionId) => !failedIds.has(transactionId)),
      );

      queryClient.setQueryData<DashboardData>(dashboardQueryKeys.data(), (previousDashboard) => {
        const currentDashboard = previousDashboard ?? dashboard.value;

        return {
          ...currentDashboard,
          pendingActions: currentDashboard.pendingActions.filter(
            (action) => !removableIds.has(action.id),
          ),
        };
      });

      if (result.failed.length > 0) {
        completeError.value = result.failed.map((item) => item.message).join(' ');
        return false;
      }

      await loadDashboard();
      return true;
    } catch (error) {
      completeError.value = resolveApiErrorMessage(
        error,
        'No fue posible completar los movimientos.',
      );
      return false;
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
