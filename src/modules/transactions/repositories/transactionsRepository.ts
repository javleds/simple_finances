import { z } from 'zod';

import { createApiClient } from '@/lib/api/apiClient';

import {
  mapTransactionApiToDomain,
  transactionApiSchema,
} from '../schemas/transactionSchemas';
import type { Transaction, TransactionWritePayload } from '../types';

const apiClient = createApiClient();
const transactionsPath = '/transactions';
const accountsPath = '/accounts';

const transactionCollectionSchema = z
  .union([
    z.array(transactionApiSchema),
    z.object({
      data: z.array(transactionApiSchema),
    }),
  ])
  .transform((payload) => ('data' in payload ? payload.data : payload));

const singleTransactionSchema = z
  .union([
    transactionApiSchema,
    z.object({
      data: transactionApiSchema,
    }),
  ])
  .transform((payload) => ('data' in payload ? payload.data : payload));

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

export function createTransactionsRepository() {
  return {
    async list(accountId?: string): Promise<Transaction[]> {
      const response = accountId
        ? await apiClient.get<unknown>(`${accountsPath}/${accountId}/transactions`)
        : await apiClient.get<unknown>(transactionsPath);
      return transactionCollectionSchema.parse(response).map(mapTransactionApiToDomain);
    },
    async create(payload: TransactionWritePayload): Promise<Transaction> {
      const response = await apiClient.post<unknown>(
        `${accountsPath}/${payload.accountId}/transactions`,
        buildWritePayload(payload),
      );
      return mapTransactionApiToDomain(singleTransactionSchema.parse(response));
    },
    async update(transactionId: string, payload: TransactionWritePayload): Promise<Transaction> {
      const response = await apiClient.put<unknown>(
        `${accountsPath}/${payload.accountId}/transactions/${transactionId}`,
        buildWritePayload(payload),
      );
      return mapTransactionApiToDomain(singleTransactionSchema.parse(response));
    },
    async remove(transactionId: string, accountId?: string): Promise<void> {
      if (accountId) {
        await apiClient.delete(`${accountsPath}/${accountId}/transactions/${transactionId}`);
        return;
      }

      await apiClient.delete(`${transactionsPath}/${transactionId}`);
    },
  };
}
