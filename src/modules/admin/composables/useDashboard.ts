import { useQuery } from '@tanstack/vue-query';
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
  },
  subscriptionsSummary: {
    annualTotal: 0,
    nearestPayment: null,
    savingsTargetToday: 0,
    subscriptionsCount: 0,
    upcomingCommitment: 0,
  },
};

export function useDashboard() {
  const loadError = ref<string | null>(null);
  const isManualLoading = ref(false);

  const dashboardQuery = useQuery({
    queryKey: dashboardQueryKeys.data(),
    queryFn: () => dashboardRepository.load(),
  });

  const dashboard = computed<DashboardData>(() => dashboardQuery.data.value ?? emptyDashboardData);
  const isLoading = computed(() => isManualLoading.value || dashboardQuery.isLoading.value);

  const hasDashboardData = computed(
    () =>
      dashboard.value.graphAccounts.length > 0 ||
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

  return {
    dashboard,
    hasDashboardData,
    isLoading,
    loadError,
    loadDashboard,
  };
}
