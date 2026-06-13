export type DashboardGraphAccount = {
  accountId: string;
  accountName: string;
  balance: number;
  color: string | null;
  isVirtual: boolean;
};

export type DashboardAccountsSummary = {
  activeAccounts: number;
  sharedAccounts: number;
  pendingTotal: number;
  virtualAccounts: number;
};

export type DashboardPendingAction = {
  id: string;
  accountId: string;
  accountName: string;
  accountColor: string | null;
  concept: string;
  amount: number;
  date: string;
};

export type DashboardSubscriptionsSummary = {
  annualTotal: number;
  subscriptionsCount: number;
};

export type DashboardData = {
  graphAccounts: DashboardGraphAccount[];
  accountsSummary: DashboardAccountsSummary;
  pendingActions: DashboardPendingAction[];
  subscriptionsSummary: DashboardSubscriptionsSummary;
};

export type DashboardPendingActionGroup = {
  accountId: string;
  accountName: string;
  accountColor: string | null;
  totalAmount: number;
  items: DashboardPendingAction[];
};

export type BatchTransactionsResult = {
  processed: number;
  failed: Array<{
    id: string;
    message: string;
  }>;
  transactionIds: string[];
};
