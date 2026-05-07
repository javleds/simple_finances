import { z } from 'zod';

import { createApiClient } from '@/lib/api/apiClient';

import {
  mapSubscriptionApiToDomain,
  subscriptionApiSchema,
} from '../schemas/subscriptionSchemas';
import type { Subscription, SubscriptionWritePayload } from '../types';

const apiClient = createApiClient();
const subscriptionsPath = '/subscriptions';

const subscriptionCollectionSchema = z
  .union([
    z.array(subscriptionApiSchema),
    z.object({
      data: z.array(subscriptionApiSchema),
    }),
  ])
  .transform((payload) => ('data' in payload ? payload.data : payload));

const singleSubscriptionSchema = z
  .union([
    subscriptionApiSchema,
    z.object({
      data: subscriptionApiSchema,
    }),
  ])
  .transform((payload) => ('data' in payload ? payload.data : payload));

function buildWritePayload(payload: SubscriptionWritePayload) {
  return {
    name: payload.name,
    amount: payload.amount,
    started_at: payload.startDate,
    frequency_unit: payload.frequencyUnit,
    frequency_type: payload.frequencyType,
    finished_at: payload.finishedAt,
    feed_account_id: payload.fundingAccountId,
  };
}

export function createSubscriptionsRepository() {
  return {
    async list(): Promise<Subscription[]> {
      const response = await apiClient.get<unknown>(subscriptionsPath);
      return subscriptionCollectionSchema.parse(response).map(mapSubscriptionApiToDomain);
    },
    async create(payload: SubscriptionWritePayload): Promise<Subscription> {
      const response = await apiClient.post<unknown>(subscriptionsPath, buildWritePayload(payload));
      return mapSubscriptionApiToDomain(singleSubscriptionSchema.parse(response));
    },
    async update(subscriptionId: string, payload: SubscriptionWritePayload): Promise<Subscription> {
      const response = await apiClient.put<unknown>(
        `${subscriptionsPath}/${subscriptionId}`,
        buildWritePayload(payload),
      );
      return mapSubscriptionApiToDomain(singleSubscriptionSchema.parse(response));
    },
    async remove(subscriptionId: string): Promise<void> {
      await apiClient.delete(`${subscriptionsPath}/${subscriptionId}`);
    },
  };
}
