import { z } from 'zod';

import { createApiClient } from '@/lib/api/apiClient';
import {
  createPaginatedCollectionSchema,
  type PaginatedCollection,
} from '@/modules/shared/lib/pagination';
import { buildQueryParams } from '@/modules/shared/lib/queryParams';

import { mapSubscriptionApiToDomain, subscriptionApiSchema } from '../schemas/subscriptionSchemas';
import {
  buildSubscriptionWritePayload,
  mapSubscriptionStatusesToFinishedFilter,
} from './subscriptionPayloads';
import type { Subscription, SubscriptionListFilters, SubscriptionWritePayload } from '../types';

const apiClient = createApiClient();
const subscriptionsPath = '/subscriptions';

const subscriptionCollectionSchema = createPaginatedCollectionSchema(subscriptionApiSchema);

const singleSubscriptionSchema = z
  .union([
    subscriptionApiSchema,
    z.object({
      data: subscriptionApiSchema,
    }),
  ])
  .transform((payload) => ('data' in payload ? payload.data : payload));

export function createSubscriptionsRepository() {
  return {
    async list(options?: {
      page?: number;
      perPage?: number;
      filters?: SubscriptionListFilters;
    }): Promise<PaginatedCollection<Subscription>> {
      const searchParams = buildQueryParams({
        page: options?.page,
        per_page: options?.perPage,
        search: options?.filters?.search,
        finished: mapSubscriptionStatusesToFinishedFilter(options?.filters?.status),
        frequency_type: options?.filters?.frequencyType,
      });
      const query = searchParams.toString();
      const response = await apiClient.get<unknown>(
        query ? `${subscriptionsPath}?${query}` : subscriptionsPath,
      );
      const parsedResponse = subscriptionCollectionSchema.parse(response);

      return {
        ...parsedResponse,
        items: parsedResponse.items.map(mapSubscriptionApiToDomain),
      };
    },
    async create(payload: SubscriptionWritePayload): Promise<Subscription> {
      const response = await apiClient.post<unknown>(
        subscriptionsPath,
        buildSubscriptionWritePayload(payload),
      );
      return mapSubscriptionApiToDomain(singleSubscriptionSchema.parse(response));
    },
    async update(subscriptionId: string, payload: SubscriptionWritePayload): Promise<Subscription> {
      const response = await apiClient.put<unknown>(
        `${subscriptionsPath}/${subscriptionId}`,
        buildSubscriptionWritePayload(payload),
      );
      return mapSubscriptionApiToDomain(singleSubscriptionSchema.parse(response));
    },
    async remove(subscriptionId: string): Promise<void> {
      await apiClient.delete(`${subscriptionsPath}/${subscriptionId}`);
    },
  };
}
