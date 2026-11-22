import { describe, expect, it } from 'vitest';
import { buildVirtualBalanceChart } from './virtualBalanceChart';
import type { DashboardGraphAccount } from '@/modules/admin/types/dashboard';
import type { VirtualAccountItem } from '@/modules/virtual-accounts/types';

const graph: DashboardGraphAccount = {
    accountId: '7',
    accountName: 'Ahorro',
    balance: '999.00',
    color: '#123456',
    isVirtual: true,
};
const summary: VirtualAccountItem = {
    accountId: '7',
    accountName: 'Ahorro',
    color: '#123456',
    currentBalance: '10500.00',
    netCapital: '10000.00',
    observedYield: '500.00',
    initialBalance: '10000.00',
    manualContributions: '12000.00',
    manualWithdrawals: '2000.00',
    latestSnapshot: null,
};

describe('virtual balance chart', () => {
    it('stacks net savings and recorded yield using the same current summary', () => {
        expect(buildVirtualBalanceChart([graph], [summary])).toEqual([
            {
                ...graph,
                balance: '10500.00',
                savings: '10000.00',
                gains: '500.00',
                unclassified: '0.00',
            },
        ]);
    });
    it('preserves negative yield instead of inflating savings', () => {
        expect(
            buildVirtualBalanceChart(
                [graph],
                [{ ...summary, currentBalance: '9800.00', observedYield: '-200.00' }],
            )[0],
        ).toMatchObject({
            balance: '9800.00',
            savings: '10000.00',
            gains: '-200.00',
            unclassified: '0.00',
        });
    });
    it('keeps historical differences separate from savings and gains', () => {
        expect(
            buildVirtualBalanceChart([graph], [{ ...summary, currentBalance: '11000.00' }])[0],
        ).toMatchObject({
            balance: '11000.00',
            savings: '10000.00',
            gains: '500.00',
            unclassified: '500.00',
        });
    });
    it('does not invent savings when a summary is unavailable', () => {
        expect(buildVirtualBalanceChart([graph], [])[0]).toMatchObject({
            balance: '999.00',
            savings: '0.00',
            gains: '0.00',
            unclassified: '999.00',
        });
    });
    it('reconciles decimal segments in cents and keeps the graph account order', () => {
        const second = { ...graph, accountId: '8' };
        const result = buildVirtualBalanceChart(
            [second, graph],
            [{ ...summary, currentBalance: '0.30', netCapital: '0.10', observedYield: '0.20' }],
        );
        expect(result.map((account) => account.accountId)).toEqual(['8', '7']);
        expect(result[1]?.unclassified).toBe('0.00');
    });
});
