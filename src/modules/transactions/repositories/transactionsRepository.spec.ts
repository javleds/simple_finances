import { describe, expect, it } from 'vitest';

import { createdTransactionResponseSchema } from './transactionsRepository';

const baseTransactionPayload = {
  id: 10,
  account_id: 20,
  account: {
    id: 20,
    name: 'Cuenta compartida',
  },
  user: {
    id: 9,
    name: 'María López',
  },
  concept: 'Compra mensual',
  amount: '1200.50',
  type: 'outcome',
  status: 'completed',
  scheduled_at: '2026-06-09T00:00:00.000000Z',
  created_at: '2026-06-09T12:00:00.000000Z',
  financial_goal_id: null,
  financial_goal: null,
};

describe('transactions repository schemas', () => {
  it('parses a shared-account create response with transaction array data', () => {
    const result = createdTransactionResponseSchema.parse({
      data: [
        {
          ...baseTransactionPayload,
          created_at: '2026-06-09T12:00:00.000000Z',
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
          created_at: '2026-06-09T12:01:00.000000Z',
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
        id: '11',
        accountId: '20',
        accountName: 'Cuenta compartida',
        concept: 'Compra mensual',
        amount: 720.3,
        type: 'income',
        status: 'pending',
        date: '2026-06-09',
        createdAt: '2026-06-09T12:01:00.000000Z',
        creatorId: '9',
        creatorName: 'María López',
        financialGoalId: null,
        financialGoalName: null,
        userPayments: {},
      },
      transactions: [
        {
          id: '11',
          accountId: '20',
          accountName: 'Cuenta compartida',
          concept: 'Compra mensual',
          amount: 720.3,
          type: 'income',
          status: 'pending',
          date: '2026-06-09',
          createdAt: '2026-06-09T12:01:00.000000Z',
          creatorId: '9',
          creatorName: 'María López',
          financialGoalId: null,
          financialGoalName: null,
          userPayments: {},
        },
        {
          id: '10',
          accountId: '20',
          accountName: 'Cuenta compartida',
          concept: 'Compra mensual',
          amount: 1200.5,
          type: 'expense',
          status: null,
          date: '2026-06-09',
          createdAt: '2026-06-09T12:00:00.000000Z',
          creatorId: '9',
          creatorName: 'María López',
          financialGoalId: null,
          financialGoalName: null,
          userPayments: {
            '7': 60,
            '8': 40,
          },
        },
      ],
      meta: {
        accountBalance: 2400,
        previousAccountBalance: null,
      },
    });
  });
});
