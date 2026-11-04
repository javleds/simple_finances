import { createApiClient } from '@/lib/api/apiClient';

import {
  virtualAccountsDashboardResponseSchema,
  virtualAccountSnapshotCollectionResponseSchema,
  virtualAccountSnapshotResponseSchema,
} from '../schemas/virtualAccountSchemas';
import type {
  VirtualAccountsDashboard,
  VirtualAccountSnapshot,
  VirtualAccountSnapshotWritePayload,
} from '../types';

const apiClient = createApiClient();

function mapSnapshotWritePayloadToApi(payload: VirtualAccountSnapshotWritePayload) {
  return {
    observed_balance: payload.observedBalance,
    observed_at: payload.observedAt,
    notes: payload.notes,
  };
}

export function createVirtualAccountsRepository() {
  return {
    async loadDashboard(): Promise<VirtualAccountsDashboard> {
      const response = await apiClient.get<unknown>('/virtual-accounts');

      return virtualAccountsDashboardResponseSchema.parse(response);
    },

    async listSnapshots(accountId: string): Promise<VirtualAccountSnapshot[]> {
      const response = await apiClient.get<unknown>(`/accounts/${accountId}/balance-snapshots`);

      return virtualAccountSnapshotCollectionResponseSchema.parse(response);
    },

    async createSnapshot(
      accountId: string,
      payload: VirtualAccountSnapshotWritePayload,
    ): Promise<VirtualAccountSnapshot> {
      const response = await apiClient.post<unknown>(
        `/accounts/${accountId}/balance-snapshots`,
        mapSnapshotWritePayloadToApi(payload),
      );

      return virtualAccountSnapshotResponseSchema.parse(response);
    },
  };
}
