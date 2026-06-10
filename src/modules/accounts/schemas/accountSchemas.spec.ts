import { describe, expect, it } from 'vitest';

import { accountApiSchema, mapAccountApiToDomain } from './accountSchemas';

describe('account schemas', () => {
  it('maps pending totals returned in the account payload', () => {
    const parsedAccount = accountApiSchema.parse({
      id: 21,
      name: 'Cuenta compartida',
      description: null,
      color: null,
      virtual: true,
      credit_card: false,
      balance: 10000,
      spent: 0,
      available_credit: null,
      credit_line: null,
      cutoff_day: null,
      feed_account_id: null,
      deleted_at: null,
      pending_by_user: [
        {
          user_id: 7,
          user_name: 'Eliot',
          amount: '250.00',
          transaction_ids: [1454, 1457],
        },
        {
          user_id: 8,
          user_name: 'Notradame',
          amount: '9,500.00',
          transaction_ids: ['1455', 1458],
        },
      ],
      users: [],
    });

    expect(mapAccountApiToDomain(parsedAccount)).toMatchObject({
      id: '21',
      pendingByUser: [
        {
          userId: '7',
          userName: 'Eliot',
          amount: 250,
          transactionIds: ['1454', '1457'],
        },
        {
          userId: '8',
          userName: 'Notradame',
          amount: 9500,
          transactionIds: ['1455', '1458'],
        },
      ],
    });
  });
});
