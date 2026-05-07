import { z } from 'zod';

import { createApiClient } from '@/lib/api/apiClient';

import {
  accountGoalApiSchema,
  mapAccountGoalApiToDomain,
} from '../schemas/accountGoalSchemas';
import type { AccountGoal, AccountGoalWritePayload } from '../schemas/accountGoalSchemas';

const apiClient = createApiClient();
const goalsPath = '/financial-goals';

const goalCollectionSchema = z
  .union([
    z.array(accountGoalApiSchema),
    z.object({
      data: z.array(accountGoalApiSchema),
    }),
  ])
  .transform((payload) => ('data' in payload ? payload.data : payload));

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
    account_id: payload.accountId,
    name: payload.name,
    target_amount: payload.targetAmount,
    deadline: payload.deadline,
  };
}

export function createAccountGoalsRepository() {
  return {
    async list(accountId: string): Promise<AccountGoal[]> {
      const response = await apiClient.get<unknown>(`${goalsPath}?account_id=${accountId}`);
      return goalCollectionSchema.parse(response).map(mapAccountGoalApiToDomain);
    },
    async create(payload: AccountGoalWritePayload): Promise<AccountGoal> {
      const response = await apiClient.post<unknown>(goalsPath, buildWritePayload(payload));
      return mapAccountGoalApiToDomain(singleGoalSchema.parse(response));
    },
    async update(goalId: string, payload: AccountGoalWritePayload): Promise<AccountGoal> {
      const response = await apiClient.put<unknown>(
        `${goalsPath}/${goalId}`,
        buildWritePayload(payload),
      );
      return mapAccountGoalApiToDomain(singleGoalSchema.parse(response));
    },
    async remove(goalId: string): Promise<void> {
      await apiClient.delete(`${goalsPath}/${goalId}`);
    },
  };
}
