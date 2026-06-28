import { describe, expect, it } from 'vitest';

import { buildTransactionWritePayload, mapTransactionTypesToApi } from './transactionPayloads';

describe('transaction payload builders', () => {
  it('maps frontend transaction form values to the API write contract', () => {
    expect(
      buildTransactionWritePayload({
        type: 'expense',
        status: null,
        concept: 'Renta',
        amount: 1200,
        accountId: '10',
        splitBetweenUsers: true,
        date: '2026-06-15',
        financialGoalId: null,
        userPayments: {
          '7': 60,
          '8': 40,
        },
      }),
    ).toEqual({
      type: 'outcome',
      status: 'completed',
      concept: 'Renta',
      amount: 1200,
      split_between_users: true,
      user_payments: [
        {
          user_id: 7,
          percentage: 60,
        },
        {
          user_id: 8,
          percentage: 40,
        },
      ],
      scheduled_at: '2026-06-15',
      financial_goal_id: null,
    });
  });

  it('maps frontend transaction filters to API transaction types', () => {
    expect(mapTransactionTypesToApi(['income', 'expense'])).toEqual(['income', 'outcome']);
    expect(mapTransactionTypesToApi([])).toBeUndefined();
    expect(mapTransactionTypesToApi(undefined)).toBeUndefined();
  });
});
