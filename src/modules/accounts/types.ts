export type AccountStatus = 'Activo' | 'Inactivo';
export type AccountKindFilter = 'credit' | 'debit';
export type AccountSurfaceFilter = 'virtual' | 'physical';

export type AccountMemberAmount = {
  userId: string;
  userName: string;
  amount: number;
};

export type AccountPendingReimbursementItem = {
  transactionId: string;
  concept: string;
  amount: number;
  occurredAt: string | null;
};

export type AccountPendingReimbursement = {
  fromUserId: string;
  fromUserName: string;
  toUserId: string;
  toUserName: string;
  amount: number;
  items: AccountPendingReimbursementItem[];
};

export type AccountMemberTransferPayload = {
  fromUserId: string;
  toUserId: string;
  amount: number;
  description?: string;
  occurredAt?: string;
};

export type AccountMemberTransferResult = {
  accountBalance: number | null;
  custodyByUser: AccountMemberAmount[];
  settlementsByUser: AccountMemberAmount[];
  pendingReimbursements: AccountPendingReimbursement[];
};

export type AccountMember = {
  id: string;
  name: string;
  email: string;
  allocationPercentage: number;
  pendingExpenses: number;
  custodyAmount: number;
  settlementAmount: number;
};

export type Account = {
  id: string;
  name: string;
  description: string;
  color: string | null;
  isVirtual: boolean;
  isCredit: boolean;
  status: AccountStatus;
  balance: number;
  totalSpent: number;
  availableCredit: number | null;
  creditLine: number | null;
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
  creditLine: number | null;
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
