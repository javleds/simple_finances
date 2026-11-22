import type { PaginatedCollection } from '@/modules/shared/lib/pagination';

export type AccountStatus = 'Activo' | 'Inactivo';
export type AccountKindFilter = 'credit' | 'debit';
export type AccountSurfaceFilter = 'virtual' | 'physical';

export type AccountMemberAmount = {
  userId: string;
  userName: string;
  amount: string;
};

export type AccountPendingReimbursementItem = {
  transactionId: string;
  concept: string;
  amount: string;
  occurredAt: string | null;
};

export type AccountPendingReimbursement = {
  fromUserId: string;
  fromUserName: string;
  toUserId: string;
  toUserName: string;
  amount: string;
  actionType: 'user_to_user' | 'custody_to_user' | 'user_to_account';
  items: AccountPendingReimbursementItem[];
};

export type AccountMemberTransferPayload = {
  fromUserId: string;
  toUserId: string;
  actionType?: AccountPendingReimbursement['actionType'];
  amount: string;
  description?: string;
  occurredAt?: string;
};

export type AccountMemberTransferResult = {
  accountBalance: string | null;
  custodyByUser: AccountMemberAmount[];
  settlementsByUser: AccountMemberAmount[];
  pendingReimbursements: AccountPendingReimbursement[];
  ledgerRows: AccountLedgerRow[];
};

export type AccountLedgerAllocation = {
  userId: string;
  userName: string | null;
  amount: string;
  percentage: number;
};

export type AccountLedgerRow = {
  id: string;
  occurredAt: string | null;
  sourceType: string;
  transactionId: string | null;
  label: string;
  description: string;
  amount: string;
  balanceAfter: string;
  custodyAfterByUser: AccountMemberAmount[];
  settlementAfterByUser: AccountMemberAmount[];
  allocations: AccountLedgerAllocation[];
};

export type AccountLedgerResult = PaginatedCollection<AccountLedgerRow>;

export type AccountLedgerRepairType = 'settlement_correction' | 'custody_correction';

export type AccountLedgerRepairPreviewEntry = {
  userId: string;
  userName: string | null;
  relatedUserId: string | null;
  relatedUserName: string | null;
  transactionId: string | null;
  type: string;
  amount: string;
  description: string | null;
};

export type AccountLedgerRepairPreview = {
  summary: string;
  ledgerEntries: AccountLedgerRepairPreviewEntry[];
};

export type AccountLedgerRepairPayload = {
  diagnosticId?: string;
  issueCode: string;
  repairType: AccountLedgerRepairType;
  fromUserId?: string;
  toUserId?: string;
  userId?: string;
  transactionId?: string | null;
  amount: string;
  description: string;
  evidence?: Record<string, unknown>;
  preview?: Record<string, unknown>;
};

export type AccountLedgerDiagnostic = {
  id: string;
  code: string;
  severity: 'warning' | 'danger' | string;
  confidence: string;
  mode: 'automatic' | 'needs_user_input' | string;
  repairType: AccountLedgerRepairType;
  title: string;
  description: string;
  targetTransactionId: string | null;
  evidence: Record<string, unknown>;
  preview: AccountLedgerRepairPreview;
  suggestedPayload: AccountLedgerRepairPayload;
  requiredFields: string[];
};

export type AccountLedgerRepair = {
  id: string;
  status: string;
  issueCode: string;
  repairType: AccountLedgerRepairType;
  confidence: string;
  actorUserId: string;
  actorUserName: string;
  targetTransactionId: string | null;
  targetTransactionConcept: string | null;
  description: string;
  amount: string;
  createdAt: string | null;
  canReverse: boolean;
  preview: AccountLedgerRepairPreview;
  result: Record<string, unknown>;
};

export type AccountLedgerDiagnosticsResult = {
  diagnostics: AccountLedgerDiagnostic[];
  repairs: AccountLedgerRepair[];
};

export type AccountMember = {
  id: string;
  name: string;
  email: string;
  allocationPercentage: number;
  pendingExpenses: string;
  custodyAmount: string;
  settlementAmount: string;
};

export type AccountVirtualCustodian = {
  id: string;
  name: string;
  custodyAmount: string;
  settlementAmount: string;
};

export type AccountCustodyTransfer = { userId: string; amount: string };

export type AccountCustodianPayload = {
  name: string;
  openingBalance: string;
  transfers: AccountCustodyTransfer[];
};

export type Account = {
  virtualCustodians?: AccountVirtualCustodian[];
  id: string;
  usesSharedCategories?: boolean;
  ownerId: string;
  name: string;
  description: string;
  color: string | null;
  isVirtual: boolean;
  isCredit: boolean;
  status: AccountStatus;
  balance: string;
  totalSpent: string | null;
  availableCredit: string | null;
  creditLine: string | null;
  closingDay: number | null;
  fundingAccountId: string | null;
  users: AccountMember[];
  custodyByUser: AccountMemberAmount[];
  settlementsByUser: AccountMemberAmount[];
  pendingReimbursements: AccountPendingReimbursement[];
};

export type AccountFormValues = {
  name: string;
  color: string;
  description: string;
  isVirtual: 'yes' | 'no';
  isCredit: 'yes' | 'no';
  creditLine: string;
  closingDay: string;
};

export type AccountWritePayload = {
  name: string;
  color: string | null;
  description: string;
  isVirtual: boolean;
  isCredit: boolean;
  creditLine: string | null;
  closingDay: number | null;
};

export type AccountListFilters = {
  search?: string;
  status?: AccountStatus[];
  kind?: AccountKindFilter[];
  surface?: AccountSurfaceFilter[];
};

export type AccountUsersListFilters = {
  search?: string;
};

export type AccountFilterSelection = {
  statuses: AccountStatus[];
  kinds: AccountKindFilter[];
  surfaces: AccountSurfaceFilter[];
};
