import { percentageSchema, moneySchema } from '@/modules/shared/lib/money';
import { z } from 'zod';

import { createApiClient } from '@/lib/api/apiClient';
import { createPaginatedCollectionSchema } from '@/modules/shared/lib/pagination';
import { buildQueryParams } from '@/modules/shared/lib/queryParams';

import { mapTransactionApiToDomain, transactionApiSchema } from '../schemas/transactionSchemas';
import { buildTransactionWritePayload, mapTransactionTypesToApi } from './transactionPayloads';
import type {
  CreatedTransactionResult,
  DeletedTransactionResult,
  Transaction,
  TransactionFacilityFilters,
  TransactionFilterCategory,
  TransactionFacilityListResult,
  TransactionFacilitySummary,
  TransactionListFilters,
  TransactionListResult,
  TransactionMemberAmount,
  TransactionMutationMeta,
  TransactionPendingReimbursement,
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

const transactionMutationCollectionDataSchema = z.union([
  transactionResponseDataSchema.transform((transaction) => [transaction]),
  z.array(transactionResponseDataSchema),
]);

const transactionIdSchema = z.union([z.string(), z.number()]).transform((value) => String(value));

const memberAmountApiSchema = z
  .object({
    user_id: transactionIdSchema,
    user_name: z.string().catch('Usuario no disponible'),
    amount: moneySchema,
  })
  .transform<TransactionMemberAmount>((payload) => ({
    userId: payload.user_id,
    userName: payload.user_name,
    amount: payload.amount,
  }));

const pendingReimbursementItemApiSchema = z
  .object({
    transaction_id: transactionIdSchema,
    concept: z.string().catch('Movimiento no disponible'),
    amount: moneySchema,
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

const pendingReimbursementApiSchema = z
  .object({
    from_user_id: transactionIdSchema,
    from_user_name: z.string().catch('Usuario no disponible'),
    to_user_id: transactionIdSchema,
    to_user_name: z.string().catch('Usuario no disponible'),
    amount: moneySchema,
    action_type: z
      .enum(['user_to_user', 'custody_to_user', 'user_to_account'])
      .optional()
      .default('user_to_user'),
    items: z.array(pendingReimbursementItemApiSchema).optional().default([]),
  })
  .transform<TransactionPendingReimbursement>((payload) => ({
    fromUserId: payload.from_user_id,
    fromUserName: payload.from_user_name,
    toUserId: payload.to_user_id,
    toUserName: payload.to_user_name,
    amount: payload.amount,
    actionType: payload.action_type,
    items: payload.items,
  }));

const ledgerAllocationApiSchema = z
  .object({
    user_id: transactionIdSchema,
    user_name: z.string().nullable().optional().default(null),
    amount: moneySchema,
    percentage: percentageSchema.default(0),
  })
  .transform((payload) => ({
    userId: payload.user_id,
    userName: payload.user_name,
    amount: payload.amount,
    percentage: payload.percentage,
  }));

const ledgerRowApiSchema = z
  .object({
    id: transactionIdSchema,
    occurred_at: z.string().nullable().optional().default(null),
    source_type: z.string(),
    transaction_id: transactionIdSchema.nullable().optional().default(null),
    label: z.string().catch('Movimiento'),
    description: z.string().catch(''),
    amount: moneySchema,
    balance_after: moneySchema,
    custody_after_by_user: z.array(memberAmountApiSchema).default([]),
    settlement_after_by_user: z.array(memberAmountApiSchema).default([]),
    allocations: z.array(ledgerAllocationApiSchema).default([]),
  })
  .transform((payload) => ({
    id: payload.id,
    occurredAt: payload.occurred_at,
    sourceType: payload.source_type,
    transactionId: payload.transaction_id,
    label: payload.label,
    description: payload.description,
    amount: payload.amount,
    balanceAfter: payload.balance_after,
    custodyAfterByUser: payload.custody_after_by_user,
    settlementAfterByUser: payload.settlement_after_by_user,
    allocations: payload.allocations,
  }));

const transactionMutationMetaPayloadSchema = z
  .object({
    account: z
      .object({
        balance: moneySchema.nullable(),
      })
      .optional(),
    previous_account: z
      .object({
        balance: moneySchema.nullable(),
      })
      .optional(),
    custody_by_user: z.array(memberAmountApiSchema).optional(),
    settlements_by_user: z.array(memberAmountApiSchema).optional(),
    pending_reimbursements: z.array(pendingReimbursementApiSchema).optional(),
    ledger_rows: z.array(ledgerRowApiSchema).optional(),
    subtransactions: z.array(transactionIdSchema).optional().default([]),
  })
  .optional()
  .transform<TransactionMutationMeta>((payload) => ({
    accountBalance: payload?.account?.balance ?? null,
    previousAccountBalance: payload?.previous_account?.balance ?? null,
    custodyByUser: payload?.custody_by_user ?? null,
    settlementsByUser: payload?.settlements_by_user ?? null,
    pendingReimbursements: payload?.pending_reimbursements ?? null,
    ledgerRows: payload?.ledger_rows ?? null,
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
  })
  .transform<TransactionMutationMeta>((payload) => payload.meta);

const transactionFacilitySummarySchema = z
  .object({
    income_total: moneySchema,
    outcome_total: moneySchema,
    balance: moneySchema,
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
  .object({
    data: transactionMutationCollectionDataSchema,
    meta: transactionMutationMetaPayloadSchema,
  })
  .transform<CreatedTransactionResult>((payload, context) => {
    const mappedTransactions = payload.data.map(mapTransactionApiToDomain);
    const transaction = mappedTransactions[0];
    const transactions = sortTransactionsByCreatedAt(mappedTransactions);

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

export function createTransactionsRepository() {
  return {
    async filterOptions(accountId?: string): Promise<TransactionFilterCategory[]> {
      const path = accountId
        ? `/accounts/${encodeURIComponent(accountId)}/transactions/filter-options`
        : '/transactions/filter-options';
      const response = await apiClient.get<unknown>(path);
      return z
        .object({
          data: z.object({
            categories: z.array(
              z.object({
                id: transactionIdSchema,
                name: z.string(),
                account_name: z.string().nullable(),
              }),
            ),
          }),
        })
        .parse(response)
        .data.categories.map((category) => ({
          id: category.id,
          name: category.name,
          accountName: category.account_name,
        }));
    },
    async list(
      accountId?: string,
      options?: { page?: number; perPage?: number; filters?: TransactionListFilters },
    ): Promise<TransactionListResult> {
      const searchParams = buildQueryParams({
        page: options?.page,
        per_page: options?.perPage,
        search: options?.filters?.search,
        type: mapTransactionTypesToApi(options?.filters?.type),
        category_ids: options?.filters?.categoryIds,
        uncategorized:
          options?.filters?.uncategorized === undefined
            ? undefined
            : Number(options.filters.uncategorized),
        has_attachments:
          options?.filters?.hasAttachments === undefined
            ? undefined
            : Number(options.filters.hasAttachments),
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
    async listFacility(options: {
      page?: number;
      perPage?: number;
      filters: TransactionFacilityFilters;
    }): Promise<TransactionFacilityListResult> {
      const searchParams = buildQueryParams({
        page: options.page,
        per_page: options.perPage,
        search: options.filters.search,
        start_date: options.filters.startDate,
        end_date: options.filters.endDate,
        category_ids: options.filters.categoryIds,
        uncategorized:
          options.filters.uncategorized === undefined
            ? undefined
            : Number(options.filters.uncategorized),
        has_attachments:
          options.filters.hasAttachments === undefined
            ? undefined
            : Number(options.filters.hasAttachments),
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
        buildTransactionWritePayload(payload),
      );
      return createdTransactionResponseSchema.parse(response);
    },
    async update(
      transactionId: string,
      payload: TransactionWritePayload,
    ): Promise<CreatedTransactionResult> {
      const response = await apiClient.put<unknown>(
        `${accountsPath}/${payload.accountId}/transactions/${transactionId}`,
        buildTransactionWritePayload(payload),
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
