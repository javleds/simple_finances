import { z } from 'zod';

import type {
  Account,
  AccountFormValues,
  AccountWritePayload,
  AccountStatus,
  AccountMember,
} from '../types';

const colorPattern = /^#([0-9a-fA-F]{6})$/;

function parseBooleanLike(value: unknown): boolean {
  if (typeof value === 'boolean') {
    return value;
  }

  if (typeof value === 'number') {
    return value === 1;
  }

  if (typeof value === 'string') {
    const normalizedValue = value.trim().toLowerCase();
    return normalizedValue === '1' || normalizedValue === 'true' || normalizedValue === 'yes';
  }

  return false;
}

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

function parseAccountStatus(value: unknown): AccountStatus {
  if (value === 'Activo' || value === 'active' || value === true || value === 1 || value === '1') {
    return 'Activo';
  }

  return 'Inactivo';
}

export const accountFormSchema = z
  .object({
    name: z.string().trim().min(1, 'El nombre es obligatorio.'),
    color: z
      .string()
      .trim()
      .refine((value) => value === '' || colorPattern.test(value), 'Usa un color hexadecimal válido.'),
    description: z.string().trim(),
    isVirtual: z.enum(['yes', 'no']),
    isCredit: z.enum(['yes', 'no']),
    creditLine: z.string().trim(),
    closingDay: z.string().trim(),
  })
  .superRefine((values, context) => {
    if (values.isCredit !== 'yes') {
      return;
    }

    const creditLine = parseNullableNumber(values.creditLine);
    const closingDay = parseNullableNumber(values.closingDay);

    if (creditLine === null) {
      context.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['creditLine'],
        message: 'La línea de crédito es obligatoria.',
      });
    } else if (creditLine < 0) {
      context.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['creditLine'],
        message: 'La línea de crédito no puede ser negativa.',
      });
    }

    if (closingDay === null) {
      context.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['closingDay'],
        message: 'El día de corte es obligatorio.',
      });
    } else if (!Number.isInteger(closingDay) || closingDay < 1 || closingDay > 31) {
      context.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['closingDay'],
        message: 'El día de corte debe estar entre 1 y 31.',
      });
    }
  });

export const accountApiSchema = z.object({
  id: z.union([z.string(), z.number()]).transform((value) => String(value)),
  name: z.string(),
  description: z.string().nullable().optional().transform((value) => value ?? ''),
  color: z.string().nullable().optional().transform((value) => value ?? null),
  virtual: z.unknown().transform(parseBooleanLike),
  credit_card: z.unknown().transform(parseBooleanLike),
  balance: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
  spent: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
  available_credit: z.unknown().transform(parseNullableNumber),
  credit_line: z.unknown().transform(parseNullableNumber),
  cutoff_day: z.unknown().transform(parseNullableNumber),
  feed_account_id: z
    .union([z.string(), z.number(), z.null(), z.undefined()])
    .transform((value) => (value === null || value === undefined ? null : String(value))),
  deleted_at: z.string().nullable().optional().transform((value) => value ?? null),
  users: z
    .array(
      z.object({
        id: z.union([z.string(), z.number()]).transform((value) => String(value)),
        name: z.string(),
        email: z.string().email().catch(''),
        percentage: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
        pending_expenses: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
      }),
    )
    .optional()
    .transform((value) => value ?? []),
});

export const accountUserApiSchema = z.object({
  id: z.union([z.string(), z.number()]).transform((value) => String(value)),
  name: z.string(),
  email: z.string().email().catch(''),
  pivot: z
    .object({
      percentage: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
    })
    .optional()
    .transform((value) => value ?? { percentage: 0 }),
});

export function createDefaultAccountFormValues(
  account?: Partial<Account> | null,
): AccountFormValues {
  return {
    name: account?.name ?? '',
    color: account?.color ?? '',
    description: account?.description ?? '',
    isVirtual: account?.isVirtual ? 'yes' : 'no',
    isCredit: account?.isCredit ? 'yes' : 'no',
    creditLine: account?.creditLine === null || account?.creditLine === undefined ? '' : String(account.creditLine),
    closingDay: account?.closingDay === null || account?.closingDay === undefined ? '' : String(account.closingDay),
  };
}

export function mapAccountApiToDomain(
  payload: z.infer<typeof accountApiSchema>,
): Account {
  return {
    id: payload.id,
    name: payload.name,
    description: payload.description,
    color: payload.color,
    isVirtual: payload.virtual,
    isCredit: payload.credit_card,
    status: parseAccountStatus(payload.deleted_at ? 'inactive' : 'active'),
    balance: payload.balance,
    totalSpent: payload.spent,
    availableCredit: payload.available_credit,
    creditLine: payload.credit_line,
    closingDay: payload.cutoff_day,
    fundingAccountId: payload.feed_account_id,
    users: payload.users.map<AccountMember>((user) => ({
      id: user.id,
      name: user.name,
      email: user.email,
      allocationPercentage: user.percentage,
      pendingExpenses: user.pending_expenses,
    })),
  };
}

export function mapAccountUserApiToDomain(
  payload: z.infer<typeof accountUserApiSchema>,
): AccountMember {
  return {
    id: payload.id,
    name: payload.name,
    email: payload.email,
    allocationPercentage: payload.pivot.percentage,
    pendingExpenses: 0,
  };
}

export function mapAccountFormToWritePayload(values: AccountFormValues): AccountWritePayload {
  return {
    name: values.name.trim(),
    color: values.color.trim() ? values.color.trim() : null,
    description: values.description.trim(),
    isVirtual: values.isVirtual === 'yes',
    isCredit: values.isCredit === 'yes',
    creditLine: values.isCredit === 'yes' ? parseNullableNumber(values.creditLine) : null,
    closingDay: values.isCredit === 'yes' ? parseNullableNumber(values.closingDay) : null,
  };
}
