import { z } from 'zod';

import { createApiClient } from '@/lib/api/apiClient';

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

const fixedIncomeCollectionSchema = z
  .union([
    z.array(fixedIncomeApiSchema),
    z.object({
      data: z.array(fixedIncomeApiSchema),
    }),
  ])
  .transform((payload) => ('data' in payload ? payload.data : payload));

const fixedIncomeItemSchema = z
  .union([
    fixedIncomeApiSchema,
    z.object({
      data: fixedIncomeApiSchema,
    }),
  ])
  .transform((payload) => ('data' in payload ? payload.data : payload));

const fixedOutcomeCollectionSchema = z
  .union([
    z.array(fixedOutcomeApiSchema),
    z.object({
      data: z.array(fixedOutcomeApiSchema),
    }),
  ])
  .transform((payload) => ('data' in payload ? payload.data : payload));

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
    async listRules(): Promise<DistributionRule[]> {
      const [fixedIncomesResponse, fixedOutcomesResponse] = await Promise.all([
        apiClient.get<unknown>('/fixed-incomes'),
        apiClient.get<unknown>('/fixed-outcomes'),
      ]);

      const rules = fixedIncomeCollectionSchema.parse(fixedIncomesResponse).map(mapFixedIncomeApiToDomain);
      const relations = fixedOutcomeCollectionSchema.parse(fixedOutcomesResponse).map(mapFixedOutcomeApiToDomain);

      return rules.map((rule) => {
        const ruleRelations = relations.filter((relation) => relation.fixedIncomeId === rule.id);
        return {
          ...rule,
          outcomesCount: ruleRelations.length,
          totalAmount: ruleRelations.reduce((sum, relation) => sum + relation.amount, 0),
        };
      });
    },
    async getRule(ruleId: string): Promise<DistributionRule> {
      const [fixedIncomeResponse, fixedOutcomesResponse] = await Promise.all([
        apiClient.get<unknown>(`/fixed-incomes/${ruleId}`),
        apiClient.get<unknown>(`/fixed-outcomes?fixed_income_id=${ruleId}`),
      ]);

      const rule = mapFixedIncomeApiToDomain(fixedIncomeItemSchema.parse(fixedIncomeResponse));
      const relations = fixedOutcomeCollectionSchema.parse(fixedOutcomesResponse).map(mapFixedOutcomeApiToDomain);

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
    async listRelations(fixedIncomeId: string): Promise<DistributionRelation[]> {
      const response = await apiClient.get<unknown>(`/fixed-outcomes?fixed_income_id=${fixedIncomeId}`);
      return fixedOutcomeCollectionSchema.parse(response).map(mapFixedOutcomeApiToDomain);
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
