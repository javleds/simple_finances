import type { PaginatedCollection } from '@/modules/shared/lib/pagination';

export type TransactionType = 'income' | 'expense';
export type TransactionStatus = 'pending' | 'completed';
export type TransactionPaymentSource = 'account_fund' | 'member_out_of_pocket';
export type TransactionListFilters = {
  search?: string;
  type?: TransactionType[];
};

export type TransactionFacilityFilters = {
  search?: string;
  startDate: string;
  endDate: string;
};

export type TransactionFacilitySummary = {
  incomeTotal: number;
  outcomeTotal: number;
  balance: number;
};

export type TransactionFacilityListResult = PaginatedCollection<Transaction> & {
  summary: TransactionFacilitySummary;
};

export type TransactionListResult = PaginatedCollection<Transaction> & {
  meta: TransactionMutationMeta;
};

export type Transaction = {
  id: string;
  accountId: string;
  accountName: string | null;
  concept: string;
  amount: number;
  type: TransactionType;
  status: TransactionStatus | null;
  date: string;
  createdAt: string | null;
  creatorId: string | null;
  creatorName: string | null;
  paidByUserId: string | null;
  paidByUserName: string | null;
  custodianUserId: string | null;
  custodianUserName: string | null;
  paymentSource: TransactionPaymentSource | null;
  financialGoalId: string | null;
  financialGoalName: string | null;
  userPayments: Record<string, number>;
  currentUserPendingReimbursementAmount: number;
};

export type TransactionFormValues = {
  type: TransactionType;
  status: 'completed';
  concept: string;
  amount: string;
  accountId: string | null;
  paidByUserId: string | null;
  custodianUserId: string | null;
  paymentSource: TransactionPaymentSource;
  splitBetweenUsers: boolean;
  date: string;
  financialGoalId: string | null;
  userPayments: Record<string, number>;
};

export type TransactionWritePayload = {
  type: TransactionType;
  status: 'completed';
  concept: string;
  amount: number;
  accountId: string;
  paidByUserId: string | null;
  custodianUserId: string | null;
  paymentSource: TransactionPaymentSource | null;
  splitBetweenUsers: boolean;
  date: string;
  financialGoalId: string | null;
  userPayments: Record<string, number>;
};

export type TransactionSubmitOptions = {
  keepOpen: boolean;
};

export type TransactionMutationMeta = {
  accountBalance: number | null;
  previousAccountBalance: number | null;
  custodyByUser: TransactionMemberAmount[] | null;
  settlementsByUser: TransactionMemberAmount[] | null;
  pendingReimbursements: TransactionPendingReimbursement[] | null;
  subtransactionIds: string[];
};

export type TransactionMemberAmount = {
  userId: string;
  userName: string;
  amount: number;
};

export type TransactionPendingReimbursement = {
  fromUserId: string;
  fromUserName: string;
  toUserId: string;
  toUserName: string;
  amount: number;
  items: TransactionPendingReimbursementItem[];
};

export type TransactionPendingReimbursementItem = {
  transactionId: string;
  concept: string;
  amount: number;
  occurredAt: string | null;
};

export type CreatedTransactionResult = {
  transaction: Transaction;
  transactions: Transaction[];
  meta: TransactionMutationMeta;
};

export type DeletedTransactionResult = {
  meta: TransactionMutationMeta;
};
