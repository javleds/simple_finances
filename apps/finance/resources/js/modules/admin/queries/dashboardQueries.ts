import type { DashboardPeriodSummaryParams } from '@/modules/admin/types/dashboard';

export const dashboardQueryKeys = {
  all: ['dashboard'] as const,
  data() {
    return [...this.all, 'data'] as const;
  },
  periodSummary(filters: DashboardPeriodSummaryParams) {
    return [
      ...this.all,
      'period-summary',
      {
        endDate: filters.endDate,
        startDate: filters.startDate,
      },
    ] as const;
  },
};
