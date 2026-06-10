import { describe, expect, it } from 'vitest';

import { createdTransactionResponseSchema } from './transactionsRepository';

const baseTransactionPayload = {
  id: 10,
  account_id: 20,
  account: {
    id: 20,
    name: 'Cuenta compartida',
  },
  concept: 'Compra mensual',
  amount: '1200.50',
  type: 'outcome',
  status: 'completed',
  scheduled_at: '2026-06-09T00:00:00.000000Z',
  financial_goal_id: null,
  financial_goal: null,
};

describe('transactions repository schemas', () => {
  it('parses a shared-account create response with transaction array data', () => {
    const result = createdTransactionResponseSchema.parse({
      data: [
        {
          ...baseTransactionPayload,
          user_payments: [
            {
              user_id: 7,
              percentage: '60.00',
            },
            {
              user_id: 8,
              percentage: '40.00',
            },
          ],
        },
        {
          ...baseTransactionPayload,
          id: 11,
          type: 'income',
          status: 'pending',
          amount: '720.30',
        },
      ],
      meta: {
        account: {
          balance: '$2,400.00',
        },
      },
    });

    expect(result).toEqual({
      transaction: {
        id: '10',
        accountId: '20',
        accountName: 'Cuenta compartida',
        concept: 'Compra mensual',
        amount: 1200.5,
        type: 'expense',
        status: null,
        date: '2026-06-09',
        financialGoalId: null,
        financialGoalName: null,
        userPayments: {
          '7': 60,
          '8': 40,
        },
      },
      meta: {
        accountBalance: 2400,
        previousAccountBalance: null,
      },
    });
  });
});
