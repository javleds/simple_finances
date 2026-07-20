import { z } from 'zod';

import {
  parseBooleanLike,
  parseEntityId,
  parseNullableNumber,
} from '@/modules/shared/lib/apiParsing';
import type {
  Account,
  AccountFormValues,
  AccountWritePayload,
  AccountStatus,
  AccountMember,
  AccountMemberAmount,
  AccountPendingReimbursement,
} from '../types';

const colorPattern = /^#([0-9a-fA-F]{6})$/;

function parseAccountStatus(value: unknown): AccountStatus {
  if (value === 'Activo' || value === 'active' || value === true || value === 1 || value === '1') {
    return 'Activo';
  }

  return 'Inactivo';
}

function resolveAccountUserId(value: {
  id?: string | null;
  user_id?: string | null;
  user?: { id?: string | null } | null;
  pivot?: { user_id?: string | null } | null;
}): string {
  return value.user_id ?? value.user?.id ?? value.pivot?.user_id ?? value.id ?? '';
}

const accountMemberAmountApiSchema = z
  .object({
    user_id: z.union([z.string(), z.number()]).transform((value) => String(value)),
    user_name: z.string().catch('Usuario no disponible'),
    amount: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
  })
  .transform<AccountMemberAmount>((payload) => ({
    userId: payload.user_id,
    userName: payload.user_name,
    amount: payload.amount,
  }));

const accountPendingReimbursementItemApiSchema = z
  .object({
    transaction_id: z.union([z.string(), z.number()]).transform((value) => String(value)),
    concept: z.string().catch('Movimiento no disponible'),
    amount: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
    occurred_at: z
      .string()
      .nullable()
      .optional()
      .transform((value) => value ?? null),
  })
  .transform((payload) => ({
    transactionId: payload.transaction_id,
    concept: payload.concept,
    amount: payload.amount,
    occurredAt: payload.occurred_at,
  }));

const accountPendingReimbursementApiSchema = z
  .object({
    from_user_id: z.union([z.string(), z.number()]).transform((value) => String(value)),
    from_user_name: z.string().catch('Usuario no disponible'),
    to_user_id: z.union([z.string(), z.number()]).transform((value) => String(value)),
    to_user_name: z.string().catch('Usuario no disponible'),
    amount: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
    items: z.array(accountPendingReimbursementItemApiSchema).optional().default([]),
  })
  .transform<AccountPendingReimbursement>((payload) => ({
    fromUserId: payload.from_user_id,
    fromUserName: payload.from_user_name,
    toUserId: payload.to_user_id,
    toUserName: payload.to_user_name,
    amount: payload.amount,
    items: payload.items,
  }));

export const accountFormSchema = z
  .object({
    name: z.string().trim().min(1, 'El nombre es obligatorio.'),
    color: z
      .string()
      .trim()
      .refine(
        (value) => value === '' || colorPattern.test(value),
        'Usa un color hexadecimal válido.',
      ),
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
  description: z
    .string()
    .nullable()
    .optional()
    .transform((value) => value ?? ''),
  color: z
    .string()
    .nullable()
    .optional()
    .transform((value) => value ?? null),
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
  deleted_at: z
    .string()
    .nullable()
    .optional()
    .transform((value) => value ?? null),
  custody_by_user: z
    .array(accountMemberAmountApiSchema)
    .optional()
    .transform((value) => value ?? []),
  settlements_by_user: z
    .array(accountMemberAmountApiSchema)
    .optional()
    .transform((value) => value ?? []),
  pending_reimbursements: z
    .array(accountPendingReimbursementApiSchema)
    .optional()
    .transform((value) => value ?? []),
  users: z
    .array(
      z
        .union([
          z
            .object({
              id: z
                .union([z.string(), z.number(), z.null(), z.undefined()])
                .transform(parseEntityId),
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
              name: z.string(),
              email: z.string().email().catch(''),
              percentage: z
                .unknown()
                .optional()
                .transform((value) => parseNullableNumber(value) ?? 0),
              pending_expenses: z
                .unknown()
                .optional()
                .transform((value) => parseNullableNumber(value) ?? 0),
              custody_amount: z
                .unknown()
                .optional()
                .transform((value) => parseNullableNumber(value) ?? 0),
              settlement_amount: z
                .unknown()
                .optional()
                .transform((value) => parseNullableNumber(value) ?? 0),
            })
            .transform((value) => ({
              id: resolveAccountUserId(value),
              name: value.name,
              email: value.email,
              percentage: value.percentage,
              pending_expenses: value.pending_expenses,
              custody_amount: value.custody_amount,
              settlement_amount: value.settlement_amount,
            })),
          z
            .object({
              id: z
                .union([z.string(), z.number(), z.null(), z.undefined()])
                .transform(parseEntityId),
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
              name: z.string(),
              email: z.string().email().catch(''),
              pivot: z
                .object({
                  percentage: z
                    .unknown()
                    .optional()
                    .transform((value) => parseNullableNumber(value) ?? 0),
                  user_id: z
                    .union([z.string(), z.number(), z.null(), z.undefined()])
                    .optional()
                    .transform(parseEntityId),
                })
                .optional()
                .transform((value) => value ?? { percentage: 0, user_id: null }),
              pending_expenses: z
                .unknown()
                .optional()
                .transform((value) => parseNullableNumber(value) ?? 0),
              custody_amount: z
                .unknown()
                .optional()
                .transform((value) => parseNullableNumber(value) ?? 0),
              settlement_amount: z
                .unknown()
                .optional()
                .transform((value) => parseNullableNumber(value) ?? 0),
            })
            .transform((value) => ({
              id: resolveAccountUserId(value),
              name: value.name,
              email: value.email,
              pivot: value.pivot,
              pending_expenses: value.pending_expenses,
              custody_amount: value.custody_amount,
              settlement_amount: value.settlement_amount,
            })),
        ])
        .transform((value) => {
          if ('percentage' in value) {
            return value;
          }

          return {
            id: value.id,
            name: value.name,
            email: value.email,
            percentage: value.pivot.percentage,
            pending_expenses: value.pending_expenses,
            custody_amount: value.custody_amount,
            settlement_amount: value.settlement_amount,
          };
        }),
    )
    .optional()
    .transform((value) => value ?? []),
});

export const accountUserApiSchema = z
  .object({
    id: z.union([z.string(), z.number(), z.null(), z.undefined()]).transform(parseEntityId),
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
    name: z.string(),
    email: z.string().email().catch(''),
    percentage: z
      .unknown()
      .optional()
      .transform((value) => parseNullableNumber(value) ?? 0),
    pending_expenses: z
      .unknown()
      .optional()
      .transform((value) => parseNullableNumber(value) ?? 0),
    custody_amount: z
      .unknown()
      .optional()
      .transform((value) => parseNullableNumber(value) ?? 0),
    settlement_amount: z
      .unknown()
      .optional()
      .transform((value) => parseNullableNumber(value) ?? 0),
    pivot: z
      .object({
        percentage: z
          .unknown()
          .optional()
          .transform((value) => parseNullableNumber(value) ?? 0),
        user_id: z
          .union([z.string(), z.number(), z.null(), z.undefined()])
          .optional()
          .transform(parseEntityId),
      })
      .optional()
      .transform((value) => value ?? { percentage: 0, user_id: null }),
  })
  .transform((value) => ({
    id: resolveAccountUserId(value),
    name: value.name,
    email: value.email,
    pending_expenses: value.pending_expenses,
    custody_amount: value.custody_amount,
    settlement_amount: value.settlement_amount,
    pivot: {
      percentage: value.pivot.percentage || value.percentage,
      user_id: value.pivot.user_id,
    },
  }));

export function createDefaultAccountFormValues(
  account?: Partial<Account> | null,
): AccountFormValues {
  return {
    name: account?.name ?? '',
    color: account?.color ?? '',
    description: account?.description ?? '',
    isVirtual: account?.isVirtual ? 'yes' : 'no',
    isCredit: account?.isCredit ? 'yes' : 'no',
    creditLine:
      account?.creditLine === null || account?.creditLine === undefined
        ? ''
        : String(account.creditLine),
    closingDay:
      account?.closingDay === null || account?.closingDay === undefined
        ? ''
        : String(account.closingDay),
  };
}

export function mapAccountApiToDomain(payload: z.infer<typeof accountApiSchema>): Account {
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
      custodyAmount: user.custody_amount,
      settlementAmount: user.settlement_amount,
    })),
    custodyByUser: payload.custody_by_user,
    settlementsByUser: payload.settlements_by_user,
    pendingReimbursements: payload.pending_reimbursements,
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
    pendingExpenses: payload.pending_expenses,
    custodyAmount: payload.custody_amount,
    settlementAmount: payload.settlement_amount,
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
