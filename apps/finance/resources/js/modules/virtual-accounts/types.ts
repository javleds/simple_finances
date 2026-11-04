export type VirtualAccountSnapshot = {
  id: string;
  accountId: string;
  observedBalance: number;
  previousBalance: number;
  delta: number;
  observedAt: string;
  notes: string | null;
  adjustmentTransactionId: string | null;
};

export type VirtualAccountSnapshotWritePayload = {
  observedBalance: number;
  observedAt: string;
  notes: string | null;
};

export type VirtualAccountSummary = {
  currentBalance: number;
  initialBalance: number;
  manualContributions: number;
  manualWithdrawals: number;
  netCapital: number;
  observedYield: number;
  accountsCount: number;
};

export type VirtualAccountItem = {
  accountId: string;
  accountName: string;
  color: string | null;
  currentBalance: number;
  initialBalance: number;
  manualContributions: number;
  manualWithdrawals: number;
  netCapital: number;
  observedYield: number;
  latestSnapshot: VirtualAccountSnapshot | null;
};

export type VirtualAccountsDashboard = {
  summary: VirtualAccountSummary;
  accounts: VirtualAccountItem[];
};
