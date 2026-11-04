import { describe, expect, it } from 'vitest';

import { accountApiSchema, mapAccountApiToDomain } from './accountSchemas';

describe('account schemas', () => {
  it('maps member summaries returned in the account payload', () => {
    const parsedAccount = accountApiSchema.parse({
      id: 21,
      user_id: 7,
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
      custody_by_user: [
        {
          user_id: 7,
          user_name: 'Eliot',
          amount: '250.00',
        },
      ],
      settlements_by_user: [
        {
          user_id: 8,
          user_name: 'Notradame',
          amount: '9,500.00',
        },
      ],
      pending_reimbursements: [
        {
          from_user_id: 7,
          from_user_name: 'Eliot',
          to_user_id: 8,
          to_user_name: 'Notradame',
          amount: '125.50',
          items: [
            {
              transaction_id: 99,
              concept: 'Consulta',
              amount: '125.50',
              occurred_at: '2026-07-02',
            },
          ],
        },
      ],
      users: [],
    });

    expect(mapAccountApiToDomain(parsedAccount)).toMatchObject({
      id: '21',
      ownerId: '7',
      custodyByUser: [
        {
          userId: '7',
          userName: 'Eliot',
          amount: 250,
        },
      ],
      settlementsByUser: [
        {
          userId: '8',
          userName: 'Notradame',
          amount: 9500,
        },
      ],
      pendingReimbursements: [
        {
          fromUserId: '7',
          fromUserName: 'Eliot',
          toUserId: '8',
          toUserName: 'Notradame',
          amount: 125.5,
          items: [
            {
              transactionId: '99',
              concept: 'Consulta',
              amount: 125.5,
              occurredAt: '2026-07-02',
            },
          ],
        },
      ],
    });
  });
});
