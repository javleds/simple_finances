import { moneySchema, parseMoney, compareMoney, money } from '@/modules/shared/lib/money';
import { z } from 'zod';

import { parseEntityId, parseNullableNumber } from '@/modules/shared/lib/apiParsing';

export type AccountGoal = {
  id: string;
  accountId: string;
  name: string;
  amount: string;
  achievedAmount: string;
  progress: number;
  deadline: string | null;
  status: 'in progress' | 'completed';
};

export type AccountGoalListFilters = {
  search?: string;
  status?: Array<'on-track' | 'at-risk' | 'completed'>;
};

export type AccountGoalFormValues = {
  name: string;
  amount: string;
  deadline: string;
  status: 'in progress' | 'completed';
};

export type AccountGoalWritePayload = {
  accountId: string;
  name: string;
  amount: string;
  deadline: string | null;
  status: 'in progress' | 'completed';
};

export const accountGoalFormSchema = z.object({
  name: z.string().trim().min(1, 'El nombre es obligatorio.'),
  amount: z
    .string()
    .trim()
    .min(1, 'La cantidad objetivo es obligatoria.')
    .refine((value) => {
      const amount = parseMoney(value);
      return amount !== null && compareMoney(amount, '0') >= 0;
    }, 'La cantidad objetivo debe ser mayor o igual a 0.'),
  deadline: z.string().trim(),
  status: z.union([z.literal('in progress'), z.literal('completed')]),
});

export const accountGoalApiSchema = z.object({
  id: z.union([z.string(), z.number()]).transform((value) => String(value)),
  account_id: z
    .union([z.string(), z.number(), z.null(), z.undefined()])
    .optional()
    .transform(parseEntityId),
  account: z
    .object({
      id: z
        .union([z.string(), z.number(), z.null(), z.undefined()])
        .optional()
        .transform(parseEntityId),
    })
    .optional()
    .nullable(),
  name: z.string(),
  amount: moneySchema,
  achieved_amount: moneySchema.default('0.00'),
  progress: z
    .unknown()
    .optional()
    .transform((value) => parseNullableNumber(value) ?? 0),
  must_completed_at: z
    .string()
    .nullable()
    .optional()
    .transform((value) => value ?? null),
  status: z.union([z.literal('in progress'), z.literal('completed')]).catch('in progress'),
});

export function createDefaultAccountGoalFormValues(
  goal?: Partial<AccountGoal> | null,
): AccountGoalFormValues {
  return {
    name: goal?.name ?? '',
    amount: goal?.amount === null || goal?.amount === undefined ? '' : String(goal.amount),
    deadline: goal?.deadline ?? '',
    status: goal?.status ?? 'in progress',
  };
}

export function mapAccountGoalApiToDomain(
  payload: z.infer<typeof accountGoalApiSchema>,
): AccountGoal {
  return {
    id: payload.id,
    accountId: payload.account_id ?? payload.account?.id ?? '',
    name: payload.name,
    amount: payload.amount,
    achievedAmount: payload.achieved_amount,
    progress: payload.progress,
    deadline: payload.must_completed_at,
    status: payload.status,
  };
}

export function mapAccountGoalFormToWritePayload(
  accountId: string,
  values: AccountGoalFormValues,
): AccountGoalWritePayload {
  return {
    accountId,
    name: values.name.trim(),
    amount: money(values.amount),
    deadline: values.deadline.trim() || null,
    status: values.status,
  };
}
