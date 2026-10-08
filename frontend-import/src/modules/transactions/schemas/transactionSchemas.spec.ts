import { describe, expect, it } from 'vitest';

import {
  mapTransactionApiToDomain,
  mapTransactionFormToWritePayload,
  transactionApiSchema,
} from './transactionSchemas';

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
  paid_by_user_id: null,
  paid_by_user: null,
  custodian_user_id: null,
  custodian_user: null,
  payment_source: null,
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
      paidByUserId: null,
      paidByUserName: null,
      custodianUserId: null,
      custodianUserName: null,
      paymentSource: null,
      currentUserPendingReimbursementAmount: 0,
      currentUserReceivableReimbursementAmount: 0,
      userPayments: {},
    });
  });

  it('maps current user receivable reimbursement amount', () => {
    const parsedTransaction = transactionApiSchema.parse({
      ...baseTransactionPayload,
      current_user_receivable_reimbursement_amount: '400.00',
    });

    expect(mapTransactionApiToDomain(parsedTransaction)).toMatchObject({
      currentUserPendingReimbursementAmount: 0,
      currentUserReceivableReimbursementAmount: 400,
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

  it('maps financial goals only for income transactions', () => {
    expect(
      mapTransactionFormToWritePayload({
        type: 'income',
        status: 'completed',
        concept: 'Depósito',
        amount: '500',
        accountId: '20',
        paidByUserId: null,
        custodianUserId: '9',
        paymentSource: 'account_fund',
        splitBetweenUsers: false,
        date: '2026-06-20',
        financialGoalId: 'goal-1',
        userPayments: {},
      }).financialGoalId,
    ).toBe('goal-1');

    expect(
      mapTransactionFormToWritePayload({
        type: 'expense',
        status: 'completed',
        concept: 'Ahorro para viaje',
        amount: '500',
        accountId: '20',
        paidByUserId: '9',
        custodianUserId: null,
        paymentSource: 'account_fund',
        splitBetweenUsers: false,
        date: '2026-06-20',
        financialGoalId: 'goal-1',
        userPayments: {},
      }).financialGoalId,
    ).toBeNull();
  });
});
