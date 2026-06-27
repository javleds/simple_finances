import type { PaginatedCollection } from '@/modules/shared/lib/pagination';

export type TransactionType = 'income' | 'expense';
export type TransactionStatus = 'pending' | 'completed';
export type TransactionListFilters = {
  search?: string;
  status?: TransactionStatus[];
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
  financialGoalId: string | null;
  financialGoalName: string | null;
  userPayments: Record<string, number>;
};

export type TransactionFormValues = {
  type: TransactionType;
  status: TransactionStatus;
  concept: string;
  amount: string;
  accountId: string | null;
  splitBetweenUsers: boolean;
  date: string;
  financialGoalId: string | null;
  userPayments: Record<string, number>;
};

export type TransactionWritePayload = {
  type: TransactionType;
  status: TransactionStatus | null;
  concept: string;
  amount: number;
  accountId: string;
  splitBetweenUsers: boolean;
  date: string;
  financialGoalId: string | null;
  userPayments: Record<string, number>;
};

export type TransactionMutationMeta = {
  accountBalance: number | null;
  previousAccountBalance: number | null;
  pendingByUser: TransactionPendingByUser[] | null;
  subtransactionIds: string[];
};

export type TransactionPendingByUser = {
  userId: string;
  userName: string;
  amount: number;
  transactionIds: string[];
};

export type CreatedTransactionResult = {
  transaction: Transaction;
  transactions: Transaction[];
  meta: TransactionMutationMeta;
};

export type DeletedTransactionResult = {
  meta: TransactionMutationMeta;
};
