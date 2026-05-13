import { z } from 'zod';

function parseNullableNumber(value: unknown): number | null {
  if (value === null || value === undefined || value === '') {
    return null;
  }

  if (typeof value === 'number') {
    return Number.isFinite(value) ? value : null;
  }

  if (typeof value === 'string') {
    const normalizedValue = value.replace(/[^0-9.-]/g, '');

    if (!normalizedValue) {
      return null;
    }

    const parsedValue = Number(normalizedValue);
    return Number.isFinite(parsedValue) ? parsedValue : null;
  }

  return null;
}

export type AccountGoal = {
  id: string;
  accountId: string;
  name: string;
  amount: number;
  progress: number;
  deadline: string | null;
  status: 'in progress' | 'completed';
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
  amount: number;
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
      const amount = parseNullableNumber(value);
      return amount !== null && amount >= 0;
    }, 'La cantidad objetivo debe ser mayor o igual a 0.'),
  deadline: z.string().trim(),
  status: z.union([z.literal('in progress'), z.literal('completed')]),
});

export const accountGoalApiSchema = z.object({
  id: z.union([z.string(), z.number()]).transform((value) => String(value)),
  account_id: z.union([z.string(), z.number()]).transform((value) => String(value)),
  name: z.string(),
  amount: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
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
    accountId: payload.account_id,
    name: payload.name,
    amount: payload.amount,
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
    amount: parseNullableNumber(values.amount) ?? 0,
    deadline: values.deadline.trim() || null,
    status: values.status,
  };
}
