import { moneySchema } from '@/modules/shared/lib/money';
import { z } from 'zod';

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
    observed_balance: moneySchema,
    previous_balance: moneySchema,
    delta: moneySchema,
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
    current_balance: moneySchema,
    initial_balance: moneySchema,
    manual_contributions: moneySchema,
    manual_withdrawals: moneySchema,
    net_capital: moneySchema,
    observed_yield: moneySchema,
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
    current_balance: moneySchema,
    initial_balance: moneySchema,
    manual_contributions: moneySchema,
    manual_withdrawals: moneySchema,
    net_capital: moneySchema,
    observed_yield: moneySchema,
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
