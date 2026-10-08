import { z } from 'zod';

import { parseNullableNumber } from '@/modules/shared/lib/apiParsing';
import type {
  VirtualAccountItem,
  VirtualAccountsDashboard,
  VirtualAccountSnapshot,
  VirtualAccountSummary,
} from '../types';

const entityIdSchema = z.union([z.string(), z.number()]).transform((value) => String(value));

const nullableStringSchema = z
  .string()
  .nullable()
  .optional()
  .transform((value) => value ?? null);

const nullableIdSchema = z
  .union([z.string(), z.number()])
  .nullable()
  .optional()
  .transform((value) => (value === null || value === undefined ? null : String(value)));

export const virtualAccountSnapshotApiSchema = z
  .object({
    id: entityIdSchema,
    account_id: entityIdSchema,
    observed_balance: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
    previous_balance: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
    delta: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
    observed_at: z.string(),
    notes: nullableStringSchema,
    adjustment_transaction_id: nullableIdSchema,
  })
  .transform<VirtualAccountSnapshot>((payload) => ({
    id: payload.id,
    accountId: payload.account_id,
    observedBalance: payload.observed_balance,
    previousBalance: payload.previous_balance,
    delta: payload.delta,
    observedAt: payload.observed_at,
    notes: payload.notes,
    adjustmentTransactionId: payload.adjustment_transaction_id,
  }));

const latestSnapshotSchema = virtualAccountSnapshotApiSchema
  .nullable()
  .optional()
  .transform((value) => value ?? null);

const virtualAccountSummaryApiSchema = z
  .object({
    current_balance: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
    initial_balance: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
    manual_contributions: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
    manual_withdrawals: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
    net_capital: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
    observed_yield: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
    accounts_count: z.coerce.number().catch(0),
  })
  .transform<VirtualAccountSummary>((payload) => ({
    currentBalance: payload.current_balance,
    initialBalance: payload.initial_balance,
    manualContributions: payload.manual_contributions,
    manualWithdrawals: payload.manual_withdrawals,
    netCapital: payload.net_capital,
    observedYield: payload.observed_yield,
    accountsCount: payload.accounts_count,
  }));

const virtualAccountItemApiSchema = z
  .object({
    account_id: entityIdSchema,
    account_name: z.string(),
    color: nullableStringSchema,
    current_balance: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
    initial_balance: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
    manual_contributions: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
    manual_withdrawals: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
    net_capital: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
    observed_yield: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
    latest_snapshot: latestSnapshotSchema,
  })
  .transform<VirtualAccountItem>((payload) => ({
    accountId: payload.account_id,
    accountName: payload.account_name,
    color: payload.color,
    currentBalance: payload.current_balance,
    initialBalance: payload.initial_balance,
    manualContributions: payload.manual_contributions,
    manualWithdrawals: payload.manual_withdrawals,
    netCapital: payload.net_capital,
    observedYield: payload.observed_yield,
    latestSnapshot: payload.latest_snapshot,
  }));

export const virtualAccountsDashboardResponseSchema = z
  .object({
    data: z.object({
      summary: virtualAccountSummaryApiSchema,
      accounts: z.array(virtualAccountItemApiSchema).default([]),
    }),
  })
  .transform<VirtualAccountsDashboard>((payload) => payload.data);

export const virtualAccountSnapshotCollectionResponseSchema = z
  .object({
    data: z.array(virtualAccountSnapshotApiSchema),
  })
  .transform((payload) => payload.data);

export const virtualAccountSnapshotResponseSchema = z
  .object({
    data: virtualAccountSnapshotApiSchema,
  })
  .transform((payload) => payload.data);
