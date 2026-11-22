import { describe, expect, it } from 'vitest';

import { virtualAccountsDashboardResponseSchema } from './virtualAccountSchemas';

describe('virtual account schemas', () => {
  it('maps virtual account summaries and latest snapshots', () => {
    const parsed = virtualAccountsDashboardResponseSchema.parse({
      data: {
        summary: {
          current_balance: '5300.00',
          initial_balance: '10000.00',
          manual_contributions: '10000.00',
          manual_withdrawals: '5000.00',
          net_capital: '5000.00',
          observed_yield: '300.00',
          accounts_count: 1,
        },
        accounts: [
          {
            account_id: 9,
            account_name: 'Investment pocket',
            color: '#00ffaa',
            current_balance: '5300.00',
            initial_balance: '10000.00',
            manual_contributions: '10000.00',
            manual_withdrawals: '5000.00',
            net_capital: '5000.00',
            observed_yield: '300.00',
            latest_snapshot: {
              id: 33,
              account_id: 9,
              observed_balance: '10300.00',
              previous_balance: '10000.00',
              delta: '300.00',
              observed_at: '2026-08-02',
              notes: null,
              adjustment_transaction_id: 44,
            },
          },
        ],
      },
    });

    expect(parsed.summary).toMatchObject({
      currentBalance: '5300.00',
      netCapital: '5000.00',
      observedYield: '300.00',
    });
    expect(parsed.accounts[0]).toMatchObject({
      accountId: '9',
      currentBalance: '5300.00',
      manualWithdrawals: '5000.00',
      latestSnapshot: {
        id: '33',
        delta: '300.00',
        adjustmentTransactionId: '44',
      },
    });
  });
});
