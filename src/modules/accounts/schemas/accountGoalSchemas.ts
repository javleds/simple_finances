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
  userName: string | null;
  name: string;
  targetAmount: number;
  currentAmount: number;
  remainingAmount: number;
  progress: number;
  deadline: string | null;
};

export type AccountGoalFormValues = {
  name: string;
  targetAmount: string;
  deadline: string;
};

export type AccountGoalWritePayload = {
  accountId: string;
  name: string;
  targetAmount: number;
  deadline: string | null;
};

export const accountGoalFormSchema = z.object({
  name: z.string().trim().min(1, 'El nombre es obligatorio.'),
  targetAmount: z
    .string()
    .trim()
    .min(1, 'La cantidad objetivo es obligatoria.')
    .refine((value) => {
      const targetAmount = parseNullableNumber(value);
      return targetAmount !== null && targetAmount >= 0;
    }, 'La cantidad objetivo debe ser mayor o igual a 0.'),
  deadline: z.string().trim().min(1, 'La fecha límite es obligatoria.'),
});

export const accountGoalApiSchema = z.object({
  id: z.union([z.string(), z.number()]).transform((value) => String(value)),
  account_id: z.union([z.string(), z.number()]).transform((value) => String(value)),
  name: z.string(),
  target_amount: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
  current_amount: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
  remaining_amount: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
  progress: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
  deadline: z.string().nullable().optional().transform((value) => value ?? null),
  user: z
    .object({
      name: z.string(),
    })
    .nullable()
    .optional()
    .transform((value) => value ?? null),
});

export function createDefaultAccountGoalFormValues(
  goal?: Partial<AccountGoal> | null,
): AccountGoalFormValues {
  return {
    name: goal?.name ?? '',
    targetAmount:
      goal?.targetAmount === null || goal?.targetAmount === undefined
        ? ''
        : String(goal.targetAmount),
    deadline: goal?.deadline ?? '',
  };
}

export function mapAccountGoalApiToDomain(
  payload: z.infer<typeof accountGoalApiSchema>,
): AccountGoal {
  return {
    id: payload.id,
    accountId: payload.account_id,
    userName: payload.user?.name ?? null,
    name: payload.name,
    targetAmount: payload.target_amount,
    currentAmount: payload.current_amount,
    remainingAmount: payload.remaining_amount,
    progress: payload.progress,
    deadline: payload.deadline,
  };
}

export function mapAccountGoalFormToWritePayload(
  accountId: string,
  values: AccountGoalFormValues,
): AccountGoalWritePayload {
  return {
    accountId,
    name: values.name.trim(),
    targetAmount: parseNullableNumber(values.targetAmount) ?? 0,
    deadline: values.deadline.trim() || null,
  };
}
