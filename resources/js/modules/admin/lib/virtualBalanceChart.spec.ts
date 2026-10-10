import { describe, expect, it } from 'vitest';
import { buildVirtualBalanceChart } from './virtualBalanceChart';
import type { DashboardGraphAccount } from '@/modules/admin/types/dashboard';
import type { VirtualAccountItem } from '@/modules/virtual-accounts/types';

const graph: DashboardGraphAccount = {
    accountId: '7',
    accountName: 'Ahorro',
    balance: 999,
    color: '#123456',
    isVirtual: true,
};
const summary: VirtualAccountItem = {
    accountId: '7',
    accountName: 'Ahorro',
    color: '#123456',
    currentBalance: 10500,
    netCapital: 10000,
    observedYield: 500,
    initialBalance: 10000,
    manualContributions: 12000,
    manualWithdrawals: 2000,
    latestSnapshot: null,
};

describe('virtual balance chart', () => {
    it('stacks net savings and recorded yield using the same current summary', () => {
        expect(buildVirtualBalanceChart([graph], [summary])).toEqual([
            { ...graph, balance: 10500, savings: 10000, gains: 500, unclassified: 0 },
        ]);
    });
    it('preserves negative yield instead of inflating savings', () => {
        expect(
            buildVirtualBalanceChart(
                [graph],
                [{ ...summary, currentBalance: 9800, observedYield: -200 }],
            )[0],
        ).toMatchObject({ balance: 9800, savings: 10000, gains: -200, unclassified: 0 });
    });
    it('keeps historical differences separate from savings and gains', () => {
        expect(
            buildVirtualBalanceChart([graph], [{ ...summary, currentBalance: 11000 }])[0],
        ).toMatchObject({ balance: 11000, savings: 10000, gains: 500, unclassified: 500 });
    });
    it('does not invent savings when a summary is unavailable', () => {
        expect(buildVirtualBalanceChart([graph], [])[0]).toMatchObject({
            balance: 999,
            savings: 0,
            gains: 0,
            unclassified: 999,
        });
    });
    it('reconciles decimal segments in cents and keeps the graph account order', () => {
        const second = { ...graph, accountId: '8' };
        const result = buildVirtualBalanceChart(
            [second, graph],
            [{ ...summary, currentBalance: 0.3, netCapital: 0.1, observedYield: 0.2 }],
        );
        expect(result.map((account) => account.accountId)).toEqual(['8', '7']);
        expect(result[1]?.unclassified).toBe(0);
    });
});
