import { z } from 'zod';

import { createApiClient } from '@/lib/api/apiClient';
import {
  createPaginatedCollectionSchema,
  type PaginatedCollection,
} from '@/modules/shared/lib/pagination';

import {
  fixedIncomeApiSchema,
  fixedOutcomeApiSchema,
  mapFixedIncomeApiToDomain,
  mapFixedOutcomeApiToDomain,
} from '../schemas/distributionSchemas';
import type {
  DistributionRelation,
  DistributionRelationWritePayload,
  DistributionRule,
  DistributionRuleWritePayload,
} from '../types';

const apiClient = createApiClient();

const fixedIncomeCollectionSchema = createPaginatedCollectionSchema(fixedIncomeApiSchema);

const fixedIncomeItemSchema = z
  .union([
    fixedIncomeApiSchema,
    z.object({
      data: fixedIncomeApiSchema,
    }),
  ])
  .transform((payload) => ('data' in payload ? payload.data : payload));

const fixedOutcomeCollectionSchema = createPaginatedCollectionSchema(fixedOutcomeApiSchema);

const fixedOutcomeItemSchema = z
  .union([
    fixedOutcomeApiSchema,
    z.object({
      data: fixedOutcomeApiSchema,
    }),
  ])
  .transform((payload) => ('data' in payload ? payload.data : payload));

export function createDistributionRepository() {
  return {
    async listRules(options?: { page?: number; perPage?: number }): Promise<PaginatedCollection<DistributionRule>> {
      const searchParams = new URLSearchParams();

      if (options?.page) {
        searchParams.set('page', String(options.page));
      }

      if (options?.perPage) {
        searchParams.set('per_page', String(options.perPage));
      }

      const query = searchParams.toString();
      const [fixedIncomesResponse, fixedOutcomesResponse] = await Promise.all([
        apiClient.get<unknown>(query ? `/fixed-incomes?${query}` : '/fixed-incomes'),
        apiClient.get<unknown>('/fixed-outcomes'),
      ]);

      const parsedRules = fixedIncomeCollectionSchema.parse(fixedIncomesResponse);
      const parsedRelations = fixedOutcomeCollectionSchema.parse(fixedOutcomesResponse);
      const rules = parsedRules.items.map(mapFixedIncomeApiToDomain);
      const relations = parsedRelations.items.map(mapFixedOutcomeApiToDomain);

      return {
        ...parsedRules,
        items: rules.map((rule) => {
          const ruleRelations = relations.filter((relation) => relation.fixedIncomeId === rule.id);
          return {
            ...rule,
            outcomesCount: ruleRelations.length,
            totalAmount: ruleRelations.reduce((sum, relation) => sum + relation.amount, 0),
          };
        }),
      };
    },
    async getRule(ruleId: string): Promise<DistributionRule> {
      const [fixedIncomeResponse, fixedOutcomesResponse] = await Promise.all([
        apiClient.get<unknown>(`/fixed-incomes/${ruleId}`),
        apiClient.get<unknown>(`/fixed-outcomes?fixed_income_id=${ruleId}`),
      ]);

      const rule = mapFixedIncomeApiToDomain(fixedIncomeItemSchema.parse(fixedIncomeResponse));
      const relations = fixedOutcomeCollectionSchema.parse(fixedOutcomesResponse).items.map(mapFixedOutcomeApiToDomain);

      return {
        ...rule,
        outcomesCount: relations.length,
        totalAmount: relations.reduce((sum, relation) => sum + relation.amount, 0),
      };
    },
    async createRule(payload: DistributionRuleWritePayload): Promise<DistributionRule> {
      const response = await apiClient.post<unknown>('/fixed-incomes', payload);
      return mapFixedIncomeApiToDomain(fixedIncomeItemSchema.parse(response));
    },
    async updateRule(ruleId: string, payload: DistributionRuleWritePayload): Promise<DistributionRule> {
      const response = await apiClient.put<unknown>(`/fixed-incomes/${ruleId}`, payload);
      return mapFixedIncomeApiToDomain(fixedIncomeItemSchema.parse(response));
    },
    async removeRule(ruleId: string): Promise<void> {
      await apiClient.delete(`/fixed-incomes/${ruleId}`);
    },
    async listRelations(
      fixedIncomeId: string,
      options?: { page?: number; perPage?: number },
    ): Promise<PaginatedCollection<DistributionRelation>> {
      const searchParams = new URLSearchParams({
        fixed_income_id: fixedIncomeId,
      });

      if (options?.page) {
        searchParams.set('page', String(options.page));
      }

      if (options?.perPage) {
        searchParams.set('per_page', String(options.perPage));
      }

      const response = await apiClient.get<unknown>(`/fixed-outcomes?${searchParams.toString()}`);
      const parsedResponse = fixedOutcomeCollectionSchema.parse(response);

      return {
        ...parsedResponse,
        items: parsedResponse.items.map(mapFixedOutcomeApiToDomain),
      };
    },
    async createRelation(payload: DistributionRelationWritePayload): Promise<DistributionRelation> {
      const response = await apiClient.post<unknown>('/fixed-outcomes', {
        fixed_income_id: Number(payload.fixedIncomeId),
        name: payload.name,
        amount: payload.amount,
        type: payload.type,
      });
      return mapFixedOutcomeApiToDomain(fixedOutcomeItemSchema.parse(response));
    },
    async updateRelation(
      relationId: string,
      payload: DistributionRelationWritePayload,
    ): Promise<DistributionRelation> {
      const response = await apiClient.put<unknown>(`/fixed-outcomes/${relationId}`, {
        fixed_income_id: Number(payload.fixedIncomeId),
        name: payload.name,
        amount: payload.amount,
        type: payload.type,
      });
      return mapFixedOutcomeApiToDomain(fixedOutcomeItemSchema.parse(response));
    },
    async removeRelation(relationId: string): Promise<void> {
      await apiClient.delete(`/fixed-outcomes/${relationId}`);
    },
  };
}
