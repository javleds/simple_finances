import { z } from 'zod';

import { createApiClient } from '@/lib/api/apiClient';
import {
  createPaginatedCollectionSchema,
  type PaginatedCollection,
} from '@/modules/shared/lib/pagination';
import { buildQueryParams } from '@/modules/shared/lib/queryParams';

import { mapTransactionApiToDomain, transactionApiSchema } from '../schemas/transactionSchemas';
import type {
  CreatedTransactionResult,
  DeletedTransactionResult,
  Transaction,
  TransactionFacilityFilters,
  TransactionFacilityListResult,
  TransactionFacilitySummary,
  TransactionListFilters,
  TransactionListResult,
  TransactionMutationMeta,
  TransactionPendingByUser,
  TransactionWritePayload,
} from '../types';

const apiClient = createApiClient();
const transactionsPath = '/transactions';
const accountsPath = '/accounts';

const transactionCollectionSchema = createPaginatedCollectionSchema(transactionApiSchema);

const transactionResponseDataSchema = z.union([
  transactionApiSchema,
  z
    .object({
      transaction: transactionApiSchema,
    })
    .transform((payload) => payload.transaction),
]);

const transactionMutationDataSchema = z.union([
  transactionResponseDataSchema,
  z
    .array(transactionResponseDataSchema)
    .transform<z.infer<typeof transactionApiSchema>>((transactions, context) => {
      const transaction = transactions[0];

      if (!transaction) {
        context.addIssue({
          code: z.ZodIssueCode.custom,
          message: 'La respuesta no incluyó una transacción creada.',
        });
        return z.NEVER;
      }

      return transaction;
    }),
]);

const transactionMutationCollectionDataSchema = z.union([
  transactionResponseDataSchema.transform((transaction) => [transaction]),
  z.array(transactionResponseDataSchema),
]);

const transactionIdSchema = z.union([z.string(), z.number()]).transform((value) => String(value));

const singleTransactionSchema = z.union([
  transactionMutationDataSchema,
  z
    .object({
      data: transactionMutationDataSchema,
    })
    .transform((payload) => payload.data),
]);

function parseNullableBalance(value: unknown): number | null {
  if (value === null || value === undefined || value === '') {
    return null;
  }

  if (typeof value === 'number') {
    return Number.isFinite(value) ? value : null;
  }

  if (typeof value === 'string') {
    const parsedValue = Number(value.replace(/[^0-9.-]/g, ''));
    return Number.isFinite(parsedValue) ? parsedValue : null;
  }

  return null;
}

const pendingByUserApiSchema = z
  .object({
    user_id: transactionIdSchema,
    user_name: z.string().catch('Usuario no disponible'),
    amount: z.unknown().transform((value) => parseNullableBalance(value) ?? 0),
    transaction_ids: z
      .array(transactionIdSchema)
      .optional()
      .transform((value) => value ?? []),
  })
  .transform<TransactionPendingByUser>((payload) => ({
    userId: payload.user_id,
    userName: payload.user_name,
    amount: payload.amount,
    transactionIds: payload.transaction_ids,
  }));

const transactionMutationMetaPayloadSchema = z
  .object({
    account: z
      .object({
        balance: z.unknown().transform(parseNullableBalance),
        pending_by_user: z.array(pendingByUserApiSchema).optional(),
      })
      .optional(),
    previous_account: z
      .object({
        balance: z.unknown().transform(parseNullableBalance),
      })
      .optional(),
    pending_by_user: z.array(pendingByUserApiSchema).optional(),
    subtransactions: z.array(transactionIdSchema).optional().default([]),
  })
  .optional()
  .transform<TransactionMutationMeta>((payload) => ({
    accountBalance: payload?.account?.balance ?? null,
    previousAccountBalance: payload?.previous_account?.balance ?? null,
    pendingByUser: payload?.pending_by_user ?? payload?.account?.pending_by_user ?? null,
    subtransactionIds: payload?.subtransactions ?? [],
  }));

const mutationMetaSchema = z
  .object({
    meta: transactionMutationMetaPayloadSchema,
  })
  .transform<TransactionMutationMeta>((payload) => payload.meta);

export const transactionListMetaSchema = z
  .object({
    meta: transactionMutationMetaPayloadSchema,
    pending_by_user: z.array(pendingByUserApiSchema).optional(),
  })
  .catch({
    meta: {
      accountBalance: null,
      previousAccountBalance: null,
      pendingByUser: null,
      subtransactionIds: [],
    },
  })
  .transform<TransactionMutationMeta>((payload) => ({
    ...payload.meta,
    pendingByUser: payload.meta.pendingByUser ?? payload.pending_by_user ?? null,
  }));

const transactionFacilitySummarySchema = z
  .object({
    income_total: z.unknown().transform((value) => parseNullableBalance(value) ?? 0),
    outcome_total: z.unknown().transform((value) => parseNullableBalance(value) ?? 0),
    balance: z.unknown().transform((value) => parseNullableBalance(value) ?? 0),
  })
  .transform<TransactionFacilitySummary>((payload) => ({
    incomeTotal: payload.income_total,
    outcomeTotal: payload.outcome_total,
    balance: payload.balance,
  }));

const transactionFacilityResponseSchema = transactionCollectionSchema.and(
  z.object({
    meta: z.object({
      summary: transactionFacilitySummarySchema,
    }),
  }),
);

function getCreatedAtTime(transaction: Transaction): number | null {
  if (!transaction.createdAt) {
    return null;
  }

  const timestamp = new Date(transaction.createdAt).getTime();

  return Number.isNaN(timestamp) ? null : timestamp;
}

function sortTransactionsByCreatedAt(transactions: Transaction[]): Transaction[] {
  return transactions
    .map((transaction, index) => ({ transaction, index }))
    .sort((left, right) => {
      const leftTime = getCreatedAtTime(left.transaction);
      const rightTime = getCreatedAtTime(right.transaction);

      if (leftTime === null && rightTime === null) {
        return left.index - right.index;
      }

      if (leftTime === null) {
        return 1;
      }

      if (rightTime === null) {
        return -1;
      }

      if (rightTime === leftTime) {
        return left.index - right.index;
      }

      return rightTime - leftTime;
    })
    .map((item) => item.transaction);
}

export const createdTransactionResponseSchema = z
  .union([
    z.object({
      data: transactionMutationCollectionDataSchema,
      meta: transactionMutationMetaPayloadSchema,
    }),
    singleTransactionSchema.transform((data) => ({
      data: [data],
      meta: {
        accountBalance: null,
        previousAccountBalance: null,
        pendingByUser: null,
        subtransactionIds: [],
      },
    })),
  ])
  .transform<CreatedTransactionResult>((payload, context) => {
    const transactions = sortTransactionsByCreatedAt(payload.data.map(mapTransactionApiToDomain));
    const transaction = transactions[0];

    if (!transaction) {
      context.addIssue({
        code: z.ZodIssueCode.custom,
        message: 'La respuesta no incluyó una transacción creada.',
      });
      return z.NEVER;
    }

    return {
      transaction,
      transactions,
      meta: payload.meta,
    };
  });

function buildWritePayload(payload: TransactionWritePayload) {
  return {
    type: payload.type === 'expense' ? 'outcome' : 'income',
    status: payload.status ?? 'completed',
    concept: payload.concept,
    amount: payload.amount,
    split_between_users: payload.splitBetweenUsers,
    user_payments: Object.entries(payload.userPayments).map(([userId, percentage]) => ({
      user_id: Number(userId),
      percentage,
    })),
    scheduled_at: payload.date,
    financial_goal_id: payload.financialGoalId,
  };
}

function mapTransactionTypesToApi(types: TransactionListFilters['type']): string[] | undefined {
  if (!types || types.length === 0) {
    return undefined;
  }

  return types.map((type) => (type === 'expense' ? 'outcome' : 'income'));
}

export function createTransactionsRepository() {
  return {
    async list(
      accountId?: string,
      options?: { page?: number; perPage?: number; filters?: TransactionListFilters },
    ): Promise<TransactionListResult> {
      const searchParams = buildQueryParams({
        page: options?.page,
        per_page: options?.perPage,
        search: options?.filters?.search,
        status: options?.filters?.status,
        type: mapTransactionTypesToApi(options?.filters?.type),
      });
      const query = searchParams.toString();
      const path = accountId ? `${accountsPath}/${accountId}/transactions` : transactionsPath;
      const response = await apiClient.get<unknown>(query ? `${path}?${query}` : path);
      const parsedResponse = transactionCollectionSchema.parse(response);

      return {
        ...parsedResponse,
        items: parsedResponse.items.map(mapTransactionApiToDomain),
        meta: transactionListMetaSchema.parse(response),
      };
    },
    async listFacility(
      options: {
        page?: number;
        perPage?: number;
        filters: TransactionFacilityFilters;
      },
    ): Promise<TransactionFacilityListResult> {
      const searchParams = buildQueryParams({
        page: options.page,
        per_page: options.perPage,
        search: options.filters.search,
        start_date: options.filters.startDate,
        end_date: options.filters.endDate,
      });
      const query = searchParams.toString();
      const response = await apiClient.get<unknown>(
        query ? `${transactionsPath}?${query}` : transactionsPath,
      );
      const parsedResponse = transactionFacilityResponseSchema.parse(response);

      return {
        ...parsedResponse,
        items: parsedResponse.items.map(mapTransactionApiToDomain),
        summary: parsedResponse.meta.summary,
      };
    },
    async create(payload: TransactionWritePayload): Promise<CreatedTransactionResult> {
      const response = await apiClient.post<unknown>(
        `${accountsPath}/${payload.accountId}/transactions`,
        buildWritePayload(payload),
      );
      return createdTransactionResponseSchema.parse(response);
    },
    async update(
      transactionId: string,
      payload: TransactionWritePayload,
    ): Promise<CreatedTransactionResult> {
      const response = await apiClient.put<unknown>(
        `${accountsPath}/${payload.accountId}/transactions/${transactionId}`,
        buildWritePayload(payload),
      );
      return createdTransactionResponseSchema.parse(response);
    },
    async remove(transactionId: string, accountId?: string): Promise<DeletedTransactionResult> {
      if (accountId) {
        const response = await apiClient.delete<unknown>(
          `${accountsPath}/${accountId}/transactions/${transactionId}`,
        );
        return {
          meta: mutationMetaSchema.parse(response),
        };
      }

      const response = await apiClient.delete<unknown>(`${transactionsPath}/${transactionId}`);

      return {
        meta: mutationMetaSchema.parse(response),
      };
    },
  };
}
