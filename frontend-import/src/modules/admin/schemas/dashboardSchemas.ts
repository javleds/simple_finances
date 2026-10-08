import { z } from 'zod';

import { parseBooleanLike, parseNullableNumber } from '@/modules/shared/lib/apiParsing';
import type {
  DashboardAccountsSummary,
  DashboardGraphAccount,
  DashboardPeriodSummary,
  DashboardSubscriptionsSummary,
} from '../types/dashboard';

const entityIdSchema = z.union([z.string(), z.number()]).transform((value) => String(value));
const nullableColorSchema = z
  .string()
  .nullable()
  .optional()
  .transform((value) => value ?? null);

const dashboardGraphItemApiSchema = z
  .object({
    account_id: entityIdSchema,
    account_name: z.string(),
    balance: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
    color: nullableColorSchema,
    is_virtual: z.unknown().optional().transform(parseBooleanLike),
  })
  .transform<DashboardGraphAccount>((payload) => ({
    accountId: payload.account_id,
    accountName: payload.account_name,
    balance: payload.balance,
    color: payload.color,
    isVirtual: payload.is_virtual,
  }));

export const dashboardGraphResponseSchema = z
  .object({
    data: z.array(dashboardGraphItemApiSchema),
  })
  .transform((payload) => payload.data);

const dashboardAccountsSummaryApiSchema = z
  .object({
    active_accounts: z.coerce.number().catch(0),
    shared_accounts: z.coerce.number().catch(0),
    virtual_accounts: z.coerce.number().catch(0),
  })
  .transform<DashboardAccountsSummary>((payload) => ({
    activeAccounts: payload.active_accounts,
    sharedAccounts: payload.shared_accounts,
    virtualAccounts: payload.virtual_accounts,
  }));

export const dashboardAccountsResponseSchema = z
  .object({
    data: z.object({
      summary: dashboardAccountsSummaryApiSchema,
    }),
  })
  .transform((payload) => ({
    summary: payload.data.summary,
  }));

export const dashboardSubscriptionsResponseSchema = z
  .object({
    data: z.object({
      annual_total: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
      savings_target_today: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
      upcoming_commitment: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
      subscriptions_count: z.coerce.number().catch(0),
      nearest_payment: z
        .object({
          subscription_id: entityIdSchema,
          name: z.string(),
          amount: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
          next_payment_date: z.string(),
          cycle_start_date: z.string(),
          target_today: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
        })
        .nullable()
        .optional()
        .transform((value) => value ?? null),
    }),
  })
  .transform<DashboardSubscriptionsSummary>((payload) => ({
    annualTotal: payload.data.annual_total,
    nearestPayment: payload.data.nearest_payment
      ? {
          amount: payload.data.nearest_payment.amount,
          cycleStartDate: payload.data.nearest_payment.cycle_start_date,
          name: payload.data.nearest_payment.name,
          nextPaymentDate: payload.data.nearest_payment.next_payment_date,
          subscriptionId: payload.data.nearest_payment.subscription_id,
          targetToday: payload.data.nearest_payment.target_today,
        }
      : null,
    savingsTargetToday: payload.data.savings_target_today,
    subscriptionsCount: payload.data.subscriptions_count,
    upcomingCommitment: payload.data.upcoming_commitment,
  }));

export const dashboardPeriodSummaryResponseSchema = z
  .object({
    data: z.object({
      period: z.object({
        start_date: z.string(),
        end_date: z.string(),
      }),
      income_total: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
      outcome_total: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
      balance: z.unknown().transform((value) => parseNullableNumber(value) ?? 0),
    }),
  })
  .transform<DashboardPeriodSummary>((payload) => ({
    period: {
      startDate: payload.data.period.start_date,
      endDate: payload.data.period.end_date,
    },
    incomeTotal: payload.data.income_total,
    outcomeTotal: payload.data.outcome_total,
    balance: payload.data.balance,
  }));
