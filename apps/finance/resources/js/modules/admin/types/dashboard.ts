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
  virtualAccounts: number;
};

export type DashboardSubscriptionsSummary = {
  annualTotal: number;
  nearestPayment: {
    amount: number;
    cycleStartDate: string;
    name: string;
    nextPaymentDate: string;
    subscriptionId: string;
    targetToday: number;
  } | null;
  savingsTargetToday: number;
  subscriptionsCount: number;
  upcomingCommitment: number;
};

export type DashboardPeriodSummary = {
  period: {
    startDate: string;
    endDate: string;
  };
  incomeTotal: number;
  outcomeTotal: number;
  balance: number;
};

export type DashboardPeriodSummaryParams = {
  startDate: string;
  endDate: string;
};

export type DashboardData = {
  graphAccounts: DashboardGraphAccount[];
  accountsSummary: DashboardAccountsSummary;
  subscriptionsSummary: DashboardSubscriptionsSummary;
};
