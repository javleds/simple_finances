import { z } from 'zod';

import type {
  Subscription,
  SubscriptionFormValues,
  SubscriptionFrequencyType,
  SubscriptionWritePayload,
} from '../types';

const frequencyTypeSchema = z.enum(['days', 'months', 'years']);

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

function parseFrequencyType(value: unknown): SubscriptionFrequencyType {
  if (value === 'days' || value === 'months' || value === 'years') {
    return value;
  }

  return 'months';
}

export const subscriptionFormSchema = z.object({
  name: z.string().trim().min(1, 'El nombre es obligatorio.'),
  amount: z
    .string()
    .trim()
    .min(1, 'La cantidad es obligatoria.')
    .refine((value) => {
      const amount = parseNullableNumber(value);
      return amount !== null && amount >= 0;
    }, 'La cantidad debe ser mayor o igual a 0.'),
  startDate: z.string().trim().min(1, 'La fecha de contratación es obligatoria.'),
  frequencyUnit: z
    .string()
    .trim()
    .min(1, 'La frecuencia es obligatoria.')
    .refine((value) => {
      const frequencyUnit = parseNullableNumber(value);
      return frequencyUnit !== null && Number.isInteger(frequencyUnit) && frequencyUnit > 0;
    }, 'La frecuencia debe ser un entero mayor a 0.'),
  frequencyType: frequencyTypeSchema,
  finishedAt: z.string().trim(),
  fundingAccountId: z.string().nullable(),
});

export const subscriptionApiSchema = z.object({
  id: z.union([z.string(), z.number()]).transform((value) => String(value)),
  name: z.string(),
  amount: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
  started_at: z.string(),
  frequency_unit: z.unknown().transform((value) => parseNullableNumber(value) ?? 1),
  frequency_type: z.unknown().transform(parseFrequencyType),
  finished_at: z.string().nullable().optional().transform((value) => value ?? null),
  canceled_at: z.string().nullable().optional().transform((value) => value ?? null),
  feed_account_id: z
    .union([z.string(), z.number(), z.null(), z.undefined()])
    .transform((value) => (value === null || value === undefined ? null : String(value))),
  next_payment_date: z.string().nullable().optional().transform((value) => value ?? null),
  previous_payment_date: z.string().nullable().optional().transform((value) => value ?? null),
  feed_account: z
    .object({
      id: z.union([z.string(), z.number()]).transform((value) => String(value)),
      name: z.string(),
    })
    .nullable()
    .optional()
    .transform((value) => value ?? null),
});

export function createDefaultSubscriptionFormValues(
  subscription?: Partial<Subscription> | null,
): SubscriptionFormValues {
  return {
    name: subscription?.name ?? '',
    amount:
      subscription?.amount === null || subscription?.amount === undefined
        ? ''
        : String(subscription.amount),
    startDate: subscription?.startDate ?? '',
    frequencyUnit:
      subscription?.frequencyUnit === null || subscription?.frequencyUnit === undefined
        ? '1'
        : String(subscription.frequencyUnit),
    frequencyType: subscription?.frequencyType ?? 'months',
    finishedAt: subscription?.finishedAt ?? '',
    fundingAccountId: subscription?.fundingAccountId ?? null,
  };
}

export function mapSubscriptionApiToDomain(
  payload: z.infer<typeof subscriptionApiSchema>,
): Subscription {
  return {
    id: payload.id,
    name: payload.name,
    amount: payload.amount,
    startDate: payload.started_at,
    frequencyUnit: payload.frequency_unit,
    frequencyType: payload.frequency_type,
    finishedAt: payload.canceled_at ?? payload.finished_at,
    fundingAccountId: payload.feed_account_id,
    fundingAccountName: payload.feed_account?.name ?? null,
    nextPaymentDate: payload.next_payment_date,
    previousPaymentDate: payload.previous_payment_date,
  };
}

export function mapSubscriptionFormToWritePayload(
  values: SubscriptionFormValues,
): SubscriptionWritePayload {
  return {
    name: values.name.trim(),
    amount: parseNullableNumber(values.amount) ?? 0,
    startDate: values.startDate,
    frequencyUnit: parseNullableNumber(values.frequencyUnit) ?? 1,
    frequencyType: values.frequencyType,
    finishedAt: values.finishedAt.trim() || null,
    fundingAccountId: values.fundingAccountId,
  };
}

export function formatSubscriptionFrequency(
  frequencyUnit: number,
  frequencyType: SubscriptionFrequencyType,
): string {
  const labelMap: Record<SubscriptionFrequencyType, string> = {
    days: frequencyUnit === 1 ? 'día' : 'días',
    months: frequencyUnit === 1 ? 'mes' : 'meses',
    years: frequencyUnit === 1 ? 'año' : 'años',
  };

  return `Cada ${frequencyUnit} ${labelMap[frequencyType]}`;
}
