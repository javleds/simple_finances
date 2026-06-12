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

export type AccountInviteStatus = 'pending' | 'accepted' | 'declined';
export type AccountInviteListFilters = {
  search?: string;
  status?: AccountInviteStatus[];
};

export type AccountInvite = {
  id: string;
  accountId: string;
  userId: string | null;
  email: string;
  percentage: number;
  status: AccountInviteStatus;
  invitedAt: string | null;
  accountName: string | null;
  invitedByName: string | null;
};

export type AccountInviteFormValues = {
  email: string;
  percentage: string;
  status: AccountInviteStatus;
};

export type AccountInviteWritePayload = {
  accountId: string;
  email: string;
  percentage: number;
  status: AccountInviteStatus;
};

function parseInviteStatus(value: unknown): AccountInviteStatus {
  if (value === 'accepted' || value === 'declined') {
    return value;
  }

  return 'pending';
}

export const accountInviteFormSchema = z.object({
  email: z.string().trim().email('Ingresa un correo electrónico válido.'),
  percentage: z
    .string()
    .trim()
    .refine((value) => {
      const percentage = parseNullableNumber(value);
      return percentage === null || (percentage >= 0 && percentage <= 100);
    }, 'El porcentaje debe estar entre 0 y 100.'),
  status: z.union([z.literal('pending'), z.literal('accepted'), z.literal('declined')]),
});

export const accountInviteApiSchema = z.object({
  id: z.union([z.string(), z.number()]).transform((value) => String(value)),
  account_id: z.union([z.string(), z.number()]).transform((value) => String(value)),
  user_id: z
    .union([z.string(), z.number(), z.null(), z.undefined()])
    .transform((value) => (value === null || value === undefined ? null : String(value))),
  email: z.string(),
  percentage: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
  status: z.unknown().transform(parseInviteStatus),
  created_at: z
    .string()
    .nullable()
    .optional()
    .transform((value) => value ?? null),
  account: z
    .object({
      name: z.string(),
    })
    .nullable()
    .optional()
    .transform((value) => value ?? null),
  user: z
    .object({
      name: z.string(),
    })
    .nullable()
    .optional()
    .transform((value) => value ?? null),
});

export function createDefaultAccountInviteFormValues(
  invite?: Partial<AccountInvite> | null,
): AccountInviteFormValues {
  return {
    email: invite?.email ?? '',
    percentage:
      invite?.percentage === null || invite?.percentage === undefined
        ? '0.0'
        : String(invite.percentage),
    status: invite?.status ?? 'pending',
  };
}

export function mapAccountInviteApiToDomain(
  payload: z.infer<typeof accountInviteApiSchema>,
): AccountInvite {
  return {
    id: payload.id,
    accountId: payload.account_id,
    userId: payload.user_id,
    email: payload.email,
    percentage: payload.percentage,
    status: payload.status,
    invitedAt: payload.created_at,
    accountName: payload.account?.name ?? null,
    invitedByName: payload.user?.name ?? null,
  };
}

export function mapAccountInviteFormToWritePayload(
  accountId: string,
  values: AccountInviteFormValues,
): AccountInviteWritePayload {
  return {
    accountId,
    email: values.email.trim(),
    percentage: parseNullableNumber(values.percentage) ?? 0,
    status: values.status,
  };
}
