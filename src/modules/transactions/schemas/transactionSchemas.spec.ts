import { describe, expect, it } from 'vitest';

import { mapTransactionApiToDomain, transactionApiSchema } from './transactionSchemas';

const baseTransactionPayload = {
  id: 10,
  account_id: 20,
  account: {
    id: 20,
    name: 'Cuenta principal',
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

describe('transaction schemas', () => {
  it('maps a personal account transaction response without user payments', () => {
    const parsedTransaction = transactionApiSchema.parse(baseTransactionPayload);

    expect(mapTransactionApiToDomain(parsedTransaction)).toEqual({
      id: '10',
      accountId: '20',
      accountName: 'Cuenta principal',
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
      userPayments: {},
    });
  });

  it('maps a shared account transaction response with flexible user payment fields', () => {
    const parsedTransaction = transactionApiSchema.parse({
      ...baseTransactionPayload,
      account_id: null,
      user_payments: [
        {
          user_id: 7,
          percentage: '60.00',
        },
        {
          user: {
            id: '8',
          },
          percentage: 40,
        },
      ],
    });

    expect(mapTransactionApiToDomain(parsedTransaction)).toMatchObject({
      accountId: '20',
      userPayments: {
        '7': 60,
        '8': 40,
      },
    });
  });
});
