import type { TransactionListFilters, TransactionWritePayload } from '../types';

export function buildTransactionWritePayload(payload: TransactionWritePayload) {
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

export function mapTransactionTypesToApi(
  types: TransactionListFilters['type'],
): string[] | undefined {
  if (!types || types.length === 0) {
    return undefined;
  }

  return types.map((type) => (type === 'expense' ? 'outcome' : 'income'));
}
