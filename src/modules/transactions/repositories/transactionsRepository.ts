import { z } from 'zod';

import { createApiClient } from '@/lib/api/apiClient';
import {
  createPaginatedCollectionSchema,
  type PaginatedCollection,
} from '@/modules/shared/lib/pagination';
import { buildQueryParams } from '@/modules/shared/lib/queryParams';

import {
  mapTransactionApiToDomain,
  transactionApiSchema,
} from '../schemas/transactionSchemas';
import type {
  CreatedTransactionResult,
  DeletedTransactionResult,
  Transaction,
  TransactionListFilters,
  TransactionMutationMeta,
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

const singleTransactionSchema = z
  .union([
    transactionResponseDataSchema,
    z.object({
      data: transactionResponseDataSchema,
    }),
  ])
  .transform((payload) => ('data' in payload ? payload.data : payload));

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

const mutationMetaSchema = z
  .object({
    meta: z
      .object({
        account: z
          .object({
            balance: z.unknown().transform(parseNullableBalance),
          })
          .optional(),
        previous_account: z
          .object({
            balance: z.unknown().transform(parseNullableBalance),
          })
          .optional(),
      })
      .optional(),
  })
  .transform<TransactionMutationMeta>((payload) => ({
    accountBalance: payload.meta?.account?.balance ?? null,
    previousAccountBalance: payload.meta?.previous_account?.balance ?? null,
  }));

const createdTransactionResponseSchema = z
  .union([
    z.object({
      data: transactionResponseDataSchema,
      meta: z
        .object({
          account: z
            .object({
              balance: z.unknown().transform(parseNullableBalance),
            })
            .optional(),
          previous_account: z
            .object({
              balance: z.unknown().transform(parseNullableBalance),
            })
            .optional(),
        })
        .optional(),
    }),
    singleTransactionSchema.transform((data) => ({ data, meta: undefined })),
  ])
  .transform<CreatedTransactionResult>((payload) => ({
    transaction: mapTransactionApiToDomain(payload.data),
    meta: {
      accountBalance: payload.meta?.account?.balance ?? null,
      previousAccountBalance: payload.meta?.previous_account?.balance ?? null,
    },
  }));

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
    ): Promise<PaginatedCollection<Transaction>> {
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
