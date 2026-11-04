import type { DashboardGraphAccount } from '@/modules/admin/types/dashboard';
import type { VirtualAccountItem } from '@/modules/virtual-accounts/types';

export type VirtualBalanceChartAccount = DashboardGraphAccount & {
    savings: number;
    gains: number;
    unclassified: number;
};

export function buildVirtualBalanceChart(
    accounts: DashboardGraphAccount[],
    virtualAccounts: VirtualAccountItem[],
): VirtualBalanceChartAccount[] {
    const summaries = new Map(virtualAccounts.map((account) => [account.accountId, account]));
    return accounts.map((account) => {
        const summary = summaries.get(account.accountId);
        const balance = summary?.currentBalance ?? account.balance;
        const savings = summary?.netCapital ?? 0;
        const gains = summary?.observedYield ?? 0;
        const unclassified =
            (Math.round(balance * 100) - Math.round(savings * 100) - Math.round(gains * 100)) / 100;
        return { ...account, balance, savings, gains, unclassified };
    });
}
