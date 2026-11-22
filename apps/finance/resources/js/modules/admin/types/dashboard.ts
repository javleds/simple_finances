export type DashboardGraphAccount = {
  accountId: string;
  accountName: string;
  balance: string;
  color: string | null;
  isVirtual: boolean;
};

export type DashboardAccountsSummary = {
  activeAccounts: number;
  sharedAccounts: number;
  virtualAccounts: number;
};

export type DashboardSubscriptionsSummary = {
  annualTotal: string;
  nearestPayment: {
    amount: string;
    cycleStartDate: string;
    name: string;
    nextPaymentDate: string;
    subscriptionId: string;
    targetToday: string;
  } | null;
  savingsTargetToday: string;
  subscriptionsCount: number;
  upcomingCommitment: string;
};

export type DashboardPeriodSummary = {
  period: {
    startDate: string;
    endDate: string;
  };
  incomeTotal: string;
  outcomeTotal: string;
  balance: string;
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
