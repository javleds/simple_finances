import { subtractMoney } from '@/modules/shared/lib/money';
import type { DashboardGraphAccount } from '@/modules/admin/types/dashboard';
import type { VirtualAccountItem } from '@/modules/virtual-accounts/types';

export type VirtualBalanceChartAccount = DashboardGraphAccount & {
    savings: string;
    gains: string;
    unclassified: string;
};

export function buildVirtualBalanceChart(
    accounts: DashboardGraphAccount[],
    virtualAccounts: VirtualAccountItem[],
): VirtualBalanceChartAccount[] {
    const summaries = new Map(virtualAccounts.map((account) => [account.accountId, account]));
    return accounts.map((account) => {
        const summary = summaries.get(account.accountId);
        const balance = summary?.currentBalance ?? account.balance;
        const savings = summary?.netCapital ?? '0.00';
        const gains = summary?.observedYield ?? '0.00';
        const unclassified = subtractMoney(subtractMoney(balance, savings), gains);
        return { ...account, balance, savings, gains, unclassified };
    });
}
