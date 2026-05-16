import { z } from 'zod';

import { createApiClient } from '@/lib/api/apiClient';
import {
  createPaginatedCollectionSchema,
  type PaginatedCollection,
} from '@/modules/shared/lib/pagination';

import {
  accountGoalApiSchema,
  mapAccountGoalApiToDomain,
} from '../schemas/accountGoalSchemas';
import type { AccountGoal, AccountGoalWritePayload } from '../schemas/accountGoalSchemas';

const apiClient = createApiClient();
const goalsPath = '/financial-goals';
const accountsPath = '/accounts';

const goalCollectionSchema = createPaginatedCollectionSchema(accountGoalApiSchema);

const singleGoalSchema = z
  .union([
    accountGoalApiSchema,
    z.object({
      data: accountGoalApiSchema,
    }),
  ])
  .transform((payload) => ('data' in payload ? payload.data : payload));

function buildWritePayload(payload: AccountGoalWritePayload) {
  return {
    name: payload.name,
    amount: payload.amount,
    must_completed_at: payload.deadline,
    status: payload.status,
  };
}

export function createAccountGoalsRepository() {
  return {
    async list(
      accountId: string,
      options?: { page?: number; perPage?: number },
    ): Promise<PaginatedCollection<AccountGoal>> {
      const searchParams = new URLSearchParams();

      if (options?.page) {
        searchParams.set('page', String(options.page));
      }

      if (options?.perPage) {
        searchParams.set('per_page', String(options.perPage));
      }

      const query = searchParams.toString();
      const response = await apiClient.get<unknown>(
        query
          ? `${accountsPath}/${accountId}/financial-goals?${query}`
          : `${accountsPath}/${accountId}/financial-goals`,
      );
      const parsedResponse = goalCollectionSchema.parse(response);

      return {
        ...parsedResponse,
        items: parsedResponse.items.map(mapAccountGoalApiToDomain),
      };
    },
    async create(payload: AccountGoalWritePayload): Promise<AccountGoal> {
      const response = await apiClient.post<unknown>(
        `${accountsPath}/${payload.accountId}/financial-goals`,
        buildWritePayload(payload),
      );
      return mapAccountGoalApiToDomain(singleGoalSchema.parse(response));
    },
    async update(goalId: string, payload: AccountGoalWritePayload): Promise<AccountGoal> {
      const response = await apiClient.put<unknown>(
        `${accountsPath}/${payload.accountId}/financial-goals/${goalId}`,
        buildWritePayload(payload),
      );
      return mapAccountGoalApiToDomain(singleGoalSchema.parse(response));
    },
    async remove(goalId: string, accountId?: string): Promise<void> {
      if (accountId) {
        await apiClient.delete(`${accountsPath}/${accountId}/financial-goals/${goalId}`);
        return;
      }

      await apiClient.delete(`${goalsPath}/${goalId}`);
    },
  };
}
