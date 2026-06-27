import { createApiClient } from '@/lib/api/apiClient';

import {
  batchTransactionsResponseSchema,
  dashboardAccountsResponseSchema,
  dashboardGraphResponseSchema,
  dashboardPeriodSummaryResponseSchema,
  dashboardSubscriptionsResponseSchema,
} from '../schemas/dashboardSchemas';
import type {
  BatchTransactionsResult,
  DashboardData,
  DashboardPeriodSummary,
  DashboardPeriodSummaryParams,
} from '../types/dashboard';

const apiClient = createApiClient();

export function createDashboardRepository() {
  return {
    async load(): Promise<DashboardData> {
      const [graphResponse, accountsResponse, subscriptionsResponse] = await Promise.all([
        apiClient.get<unknown>('/dashboard/graph'),
        apiClient.get<unknown>('/dashboard/accounts'),
        apiClient.get<unknown>('/dashboard/subscriptions'),
      ]);
      const graphAccounts = dashboardGraphResponseSchema.parse(graphResponse);
      const accounts = dashboardAccountsResponseSchema.parse(accountsResponse);
      const subscriptionsSummary =
        dashboardSubscriptionsResponseSchema.parse(subscriptionsResponse);

      return {
        graphAccounts,
        accountsSummary: accounts.summary,
        pendingActions: accounts.pendingActions,
        subscriptionsSummary,
      };
    },

    async completePendingTransactions(transactionIds: string[]): Promise<BatchTransactionsResult> {
      const response = await apiClient.post<unknown>('/batch/transactions', {
        action: 'complete',
        transaction_ids: transactionIds,
      });

      return batchTransactionsResponseSchema.parse(response);
    },

    async loadPeriodSummary(
      params: DashboardPeriodSummaryParams,
    ): Promise<DashboardPeriodSummary> {
      const searchParams = new URLSearchParams({
        start_date: params.startDate,
        end_date: params.endDate,
      });
      const response = await apiClient.get<unknown>(
        `/dashboard/period-summary?${searchParams.toString()}`,
      );

      return dashboardPeriodSummaryResponseSchema.parse(response);
    },
  };
}
