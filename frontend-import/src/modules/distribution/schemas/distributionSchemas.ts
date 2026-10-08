import { z } from 'zod';

import { parseNullableNumber } from '@/modules/shared/lib/apiParsing';
import type {
  DistributionFrequency,
  DistributionRelation,
  DistributionRelationFormValues,
  DistributionRelationType,
  DistributionRelationWritePayload,
  DistributionRule,
  DistributionRuleFormValues,
  DistributionRuleWritePayload,
} from '../types';

function parseFrequency(value: unknown): DistributionFrequency {
  return value === 'semi_monthly' ? 'semi_monthly' : 'monthly';
}

function parseRelationType(value: unknown): DistributionRelationType {
  return value === 'savings' ? 'savings' : 'transfer';
}

export const distributionRuleFormSchema = z.object({
  name: z.string().trim().min(1, 'El nombre es obligatorio.'),
  frequency: z.union([z.literal('monthly'), z.literal('semi_monthly')]),
});

export const distributionRelationFormSchema = z.object({
  name: z.string().trim().min(1, 'El concepto es obligatorio.'),
  amount: z
    .string()
    .trim()
    .min(1, 'La cantidad es obligatoria.')
    .refine((value) => {
      const amount = parseNullableNumber(value);
      return amount !== null && amount >= 0;
    }, 'La cantidad debe ser mayor o igual a 0.'),
  type: z.union([z.literal('savings'), z.literal('transfer')]),
});

export const fixedIncomeApiSchema = z.object({
  id: z.union([z.string(), z.number()]).transform((value) => String(value)),
  name: z.string(),
  frequency: z.unknown().transform(parseFrequency),
});

export const fixedOutcomeApiSchema = z.object({
  id: z.union([z.string(), z.number()]).transform((value) => String(value)),
  fixed_income_id: z.union([z.string(), z.number()]).transform((value) => String(value)),
  name: z.string(),
  amount: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
  type: z.unknown().transform(parseRelationType),
});

export function mapFixedIncomeApiToDomain(
  payload: z.infer<typeof fixedIncomeApiSchema>,
): DistributionRule {
  return {
    id: payload.id,
    name: payload.name,
    frequency: payload.frequency,
    outcomesCount: 0,
    totalAmount: 0,
  };
}

export function mapFixedOutcomeApiToDomain(
  payload: z.infer<typeof fixedOutcomeApiSchema>,
): DistributionRelation {
  return {
    id: payload.id,
    fixedIncomeId: payload.fixed_income_id,
    name: payload.name,
    amount: payload.amount,
    type: payload.type,
  };
}

export function createDefaultDistributionRuleFormValues(
  rule?: Partial<DistributionRule> | null,
): DistributionRuleFormValues {
  return {
    name: rule?.name ?? '',
    frequency: rule?.frequency ?? 'monthly',
  };
}

export function createDefaultDistributionRelationFormValues(
  relation?: Partial<DistributionRelation> | null,
): DistributionRelationFormValues {
  return {
    name: relation?.name ?? '',
    amount:
      relation?.amount === null || relation?.amount === undefined ? '' : String(relation.amount),
    type: relation?.type ?? 'transfer',
  };
}

export function mapDistributionRuleFormToWritePayload(
  values: DistributionRuleFormValues,
): DistributionRuleWritePayload {
  return {
    name: values.name.trim(),
    frequency: values.frequency,
  };
}

export function mapDistributionRelationFormToWritePayload(
  fixedIncomeId: string,
  values: DistributionRelationFormValues,
): DistributionRelationWritePayload {
  return {
    fixedIncomeId,
    name: values.name.trim(),
    amount: parseNullableNumber(values.amount) ?? 0,
    type: values.type,
  };
}

export function formatDistributionFrequency(value: DistributionFrequency): string {
  return value === 'semi_monthly' ? 'Quincenal' : 'Mensual';
}

export function formatDistributionRelationType(value: DistributionRelationType): string {
  return value === 'savings' ? 'Ahorro' : 'Transferencia';
}
