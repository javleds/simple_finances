import { z } from 'zod';

import type {
  Subscription,
  SubscriptionFormValues,
  SubscriptionFrequencyUnit,
  SubscriptionWritePayload,
} from '../types';

const frequencyUnitSchema = z.enum(['day', 'week', 'month', 'year']);

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

function parseFrequencyUnit(value: unknown): SubscriptionFrequencyUnit {
  if (value === 'day' || value === 'week' || value === 'month' || value === 'year') {
    return value;
  }

  return 'month';
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
  frequencyEvery: z
    .string()
    .trim()
    .min(1, 'La frecuencia es obligatoria.')
    .refine((value) => {
      const frequencyEvery = parseNullableNumber(value);
      return frequencyEvery !== null && Number.isInteger(frequencyEvery) && frequencyEvery > 0;
    }, 'La frecuencia debe ser un entero mayor a 0.'),
  frequencyUnit: frequencyUnitSchema,
  cancellationDate: z.string().trim().default(''),
  fundingAccountId: z.string().nullable(),
});

export const subscriptionApiSchema = z.object({
  id: z.union([z.string(), z.number()]).transform((value) => String(value)),
  name: z.string(),
  amount: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
  start_date: z.string(),
  frequency_every: z.unknown().transform((value) => parseNullableNumber(value) ?? 1),
  frequency_unit: z.unknown().transform(parseFrequencyUnit),
  cancellation_date: z.string().nullable().optional().transform((value) => value ?? null),
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
    frequencyEvery:
      subscription?.frequencyEvery === null || subscription?.frequencyEvery === undefined
        ? '1'
        : String(subscription.frequencyEvery),
    frequencyUnit: subscription?.frequencyUnit ?? 'month',
    cancellationDate: subscription?.cancellationDate ?? '',
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
    startDate: payload.start_date,
    frequencyEvery: payload.frequency_every,
    frequencyUnit: payload.frequency_unit,
    cancellationDate: payload.cancellation_date,
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
    frequencyEvery: parseNullableNumber(values.frequencyEvery) ?? 1,
    frequencyUnit: values.frequencyUnit,
    cancellationDate: values.cancellationDate.trim() || null,
    fundingAccountId: values.fundingAccountId,
  };
}

export function formatSubscriptionFrequency(
  frequencyEvery: number,
  frequencyUnit: SubscriptionFrequencyUnit,
): string {
  const labelMap: Record<SubscriptionFrequencyUnit, string> = {
    day: frequencyEvery === 1 ? 'día' : 'días',
    week: frequencyEvery === 1 ? 'semana' : 'semanas',
    month: frequencyEvery === 1 ? 'mes' : 'meses',
    year: frequencyEvery === 1 ? 'año' : 'años',
  };

  return `Cada ${frequencyEvery} ${labelMap[frequencyUnit]}`;
}
