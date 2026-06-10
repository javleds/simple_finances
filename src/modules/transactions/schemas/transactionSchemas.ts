import { z } from 'zod';

import type {
  Transaction,
  TransactionFormValues,
  TransactionStatus,
  TransactionType,
  TransactionWritePayload,
} from '../types';

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

function parseType(value: unknown): TransactionType {
  if (value === 'income') {
    return 'income';
  }

  if (value === 'expense' || value === 'outcome') {
    return 'expense';
  }

  return 'expense';
}

function parseStatus(value: unknown): TransactionStatus | null {
  if (value === 'pending' || value === 'completed') {
    return value;
  }

  return null;
}

function parseEntityId(value: unknown): string | null {
  if (value === null || value === undefined) {
    return null;
  }

  if (typeof value === 'string' || typeof value === 'number') {
    return String(value);
  }

  return null;
}

function normalizeUserPayments(
  userPayments: Record<string, number>,
): Record<string, number> {
  return Object.entries(userPayments).reduce<Record<string, number>>((accumulator, [userId, value]) => {
    if (Number.isFinite(value)) {
      accumulator[userId] = Number(value.toFixed(2));
    }

    return accumulator;
  }, {});
}

export const transactionFormSchema = z
  .object({
    type: z.enum(['income', 'expense']),
    status: z.enum(['pending', 'completed']),
    concept: z.string().trim().min(1, 'El concepto es obligatorio.'),
    amount: z
      .string()
      .trim()
      .min(1, 'La cantidad es obligatoria.')
      .refine((value) => {
        const amount = parseNullableNumber(value);
        return amount !== null && amount >= 0;
      }, 'La cantidad debe ser mayor o igual a 0.'),
    accountId: z.string().nullable(),
    splitBetweenUsers: z.boolean(),
    date: z.string().trim().min(1, 'La fecha es obligatoria.'),
    financialGoalId: z.string().nullable(),
    userPayments: z.record(z.string(), z.number()),
  })
  .superRefine((values, context) => {
    if (!values.accountId) {
      context.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['accountId'],
        message: 'La cuenta es obligatoria.',
      });
    }

    if (values.type === 'expense' && values.financialGoalId) {
      context.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['financialGoalId'],
        message: 'La meta financiera sólo aplica a ingresos.',
      });
    }

    if (values.type === 'expense' && values.splitBetweenUsers) {
      const total = Object.values(values.userPayments).reduce((sum, value) => sum + value, 0);

      if (Math.abs(total - 100) > 0.01) {
        context.addIssue({
          code: z.ZodIssueCode.custom,
          path: ['userPayments'],
          message: 'La suma de porcentajes debe ser exactamente 100.',
        });
      }
    }
  });

export const transactionApiSchema = z.object({
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
      name: z.string().optional().catch(''),
    })
    .nullable()
    .optional()
    .transform((value) => value ?? null),
  user: z
    .object({
      name: z.string().nullable().optional().transform((value) => value ?? null),
    })
    .nullable()
    .optional()
    .transform((value) => value ?? null),
  created_by: z
    .object({
      name: z.string().nullable().optional().transform((value) => value ?? null),
    })
    .nullable()
    .optional()
    .transform((value) => value ?? null),
  creator: z
    .object({
      name: z.string().nullable().optional().transform((value) => value ?? null),
    })
    .nullable()
    .optional()
    .transform((value) => value ?? null),
  concept: z.string(),
  amount: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
  type: z.unknown().transform(parseType),
  status: z.unknown().transform(parseStatus),
  scheduled_at: z.string(),
  created_at: z
    .string()
    .nullable()
    .optional()
    .transform((value) => value ?? null),
  financial_goal_id: z
    .union([z.string(), z.number(), z.null(), z.undefined()])
    .transform((value) => (value === null || value === undefined ? null : String(value))),
  financial_goal: z
    .object({
      name: z.string(),
    })
    .nullable()
    .optional()
    .transform((value) => value ?? null),
  user_payments: z
    .array(
      z
        .object({
          user_id: z
            .union([z.string(), z.number(), z.null(), z.undefined()])
            .optional()
            .transform(parseEntityId),
          user: z
            .object({
              id: z
                .union([z.string(), z.number(), z.null(), z.undefined()])
                .optional()
                .transform(parseEntityId),
            })
            .optional()
            .nullable(),
          percentage: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
        })
        .transform((value) => ({
          user_id: value.user_id ?? value.user?.id ?? '',
          percentage: value.percentage,
        })),
    )
    .optional()
    .transform((value) => value ?? []),
});

export function createDefaultTransactionFormValues(
  transaction?: Partial<Transaction> | null,
  lockedAccountId?: string | null,
): TransactionFormValues {
  return {
    type: transaction?.type ?? 'expense',
    status: transaction?.status ?? 'completed',
    concept: transaction?.concept ?? '',
    amount:
      transaction?.amount === null || transaction?.amount === undefined
        ? ''
        : String(transaction.amount),
    accountId: transaction?.accountId ?? lockedAccountId ?? null,
    splitBetweenUsers: Object.keys(transaction?.userPayments ?? {}).length > 0,
    date: transaction?.date ?? new Date().toISOString().slice(0, 10),
    financialGoalId: transaction?.financialGoalId ?? null,
    userPayments: transaction?.userPayments ?? {},
  };
}

export function mapTransactionApiToDomain(
  payload: z.infer<typeof transactionApiSchema>,
): Transaction {
  return {
    id: payload.id,
    accountId: payload.account_id ?? payload.account?.id ?? '',
    accountName: payload.account?.name ?? null,
    concept: payload.concept,
    amount: payload.amount,
    type: payload.type,
    status: payload.type === 'income' ? payload.status : null,
    date: payload.scheduled_at.slice(0, 10),
    createdAt: payload.created_at,
    creatorName: payload.user?.name ?? payload.created_by?.name ?? payload.creator?.name ?? null,
    financialGoalId: payload.financial_goal_id,
    financialGoalName: payload.financial_goal?.name ?? null,
    userPayments: payload.user_payments.reduce<Record<string, number>>((accumulator, payment) => {
      if (!payment.user_id) {
        return accumulator;
      }

      accumulator[payment.user_id] = payment.percentage;
      return accumulator;
    }, {}),
  };
}

export function mapTransactionFormToWritePayload(
  values: TransactionFormValues,
): TransactionWritePayload {
  return {
    type: values.type,
    status: values.type === 'income' ? values.status : null,
    concept: values.concept.trim(),
    amount: parseNullableNumber(values.amount) ?? 0,
    accountId: values.accountId ?? '',
    splitBetweenUsers: values.type === 'expense' && values.splitBetweenUsers,
    date: values.date,
    financialGoalId: values.type === 'income' ? values.financialGoalId : null,
    userPayments:
      values.type === 'expense' && values.splitBetweenUsers
        ? normalizeUserPayments(values.userPayments)
        : {},
  };
}
