export type VirtualAccountSnapshot = {
  id: string;
  accountId: string;
  observedBalance: string;
  previousBalance: string;
  delta: string;
  observedAt: string;
  notes: string | null;
  adjustmentTransactionId: string | null;
};

export type VirtualAccountSnapshotWritePayload = {
  observedBalance: string;
  observedAt: string;
  notes: string | null;
};

export type VirtualAccountSummary = {
  currentBalance: string;
  initialBalance: string;
  manualContributions: string;
  manualWithdrawals: string;
  netCapital: string;
  observedYield: string;
  accountsCount: number;
};

export type VirtualAccountItem = {
  accountId: string;
  accountName: string;
  color: string | null;
  currentBalance: string;
  initialBalance: string;
  manualContributions: string;
  manualWithdrawals: string;
  netCapital: string;
  observedYield: string;
  latestSnapshot: VirtualAccountSnapshot | null;
};

export type VirtualAccountsDashboard = {
  summary: VirtualAccountSummary;
  accounts: VirtualAccountItem[];
};
