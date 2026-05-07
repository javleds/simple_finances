import { z } from 'zod';

import { createApiClient } from '@/lib/api/apiClient';

import {
  mapTransactionApiToDomain,
  transactionApiSchema,
} from '../schemas/transactionSchemas';
import type { Transaction, TransactionWritePayload } from '../types';

const apiClient = createApiClient();
const transactionsPath = '/transactions';

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
    status: payload.status,
    concept: payload.concept,
    amount: payload.amount,
    account_id: payload.accountId,
    divide_between_users: payload.splitBetweenUsers,
    user_payments: payload.userPayments,
    date: payload.date,
    financial_goal_id: payload.financialGoalId,
  };
}

export function createTransactionsRepository() {
  return {
    async list(accountId?: string): Promise<Transaction[]> {
      const query = accountId ? `?account_id=${accountId}` : '';
      const response = await apiClient.get<unknown>(`${transactionsPath}${query}`);
      return transactionCollectionSchema.parse(response).map(mapTransactionApiToDomain);
    },
    async create(payload: TransactionWritePayload): Promise<Transaction> {
      const response = await apiClient.post<unknown>(transactionsPath, buildWritePayload(payload));
      return mapTransactionApiToDomain(singleTransactionSchema.parse(response));
    },
    async update(transactionId: string, payload: TransactionWritePayload): Promise<Transaction> {
      const response = await apiClient.put<unknown>(
        `${transactionsPath}/${transactionId}`,
        buildWritePayload(payload),
      );
      return mapTransactionApiToDomain(singleTransactionSchema.parse(response));
    },
    async remove(transactionId: string): Promise<void> {
      await apiClient.delete(`${transactionsPath}/${transactionId}`);
    },
  };
}
