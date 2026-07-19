import { describe, expect, it } from 'vitest';

import {
  createdTransactionResponseSchema,
  transactionListMetaSchema,
} from './transactionsRepository';

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
  it('parses member summary from a transaction list response meta', () => {
    expect(
      transactionListMetaSchema.parse({
        data: [],
        meta: {
          current_page: 1,
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
            },
          ],
        },
      }),
    ).toEqual({
      accountBalance: null,
      previousAccountBalance: null,
      subtransactionIds: [],
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
        },
      ],
    });
  });

  it('parses account balance from transaction list response meta', () => {
    expect(
      transactionListMetaSchema.parse({
        data: [],
        meta: {
          account: {
            balance: '10,000.00',
          },
        },
      }),
    ).toEqual({
      accountBalance: 10000,
      previousAccountBalance: null,
      subtransactionIds: [],
      custodyByUser: null,
      settlementsByUser: null,
      pendingReimbursements: null,
    });
  });

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
          status: 'completed',
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
        status: 'completed',
        date: '2026-06-09',
        createdAt: '2026-06-09T12:01:00.000000Z',
        creatorId: '9',
        creatorName: 'María López',
        financialGoalId: null,
        financialGoalName: null,
        paidByUserId: null,
        paidByUserName: null,
        custodianUserId: null,
        custodianUserName: null,
        paymentSource: null,
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
          status: 'completed',
          date: '2026-06-09',
          createdAt: '2026-06-09T12:01:00.000000Z',
          creatorId: '9',
          creatorName: 'María López',
          financialGoalId: null,
          financialGoalName: null,
          paidByUserId: null,
          paidByUserName: null,
          custodianUserId: null,
          custodianUserName: null,
          paymentSource: null,
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
          paidByUserId: null,
          paidByUserName: null,
          custodianUserId: null,
          custodianUserName: null,
          paymentSource: null,
          userPayments: {
            '7': 60,
            '8': 40,
          },
        },
      ],
      meta: {
        accountBalance: 2400,
        previousAccountBalance: null,
        subtransactionIds: [],
        custodyByUser: null,
        settlementsByUser: null,
        pendingReimbursements: null,
      },
    });
  });

  it('parses subtransaction ids from mutation meta', () => {
    const result = createdTransactionResponseSchema.parse({
      data: baseTransactionPayload,
      meta: {
        subtransactions: [11, '12'],
      },
    });

    expect(result.meta.subtransactionIds).toEqual(['11', '12']);
  });
});
