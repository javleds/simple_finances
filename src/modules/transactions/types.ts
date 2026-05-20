export type TransactionType = 'income' | 'expense';
export type TransactionStatus = 'pending' | 'completed';
export type TransactionListFilters = {
  search?: string;
  status?: TransactionStatus[];
  type?: TransactionType[];
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
};

export type CreatedTransactionResult = {
  transaction: Transaction;
  meta: TransactionMutationMeta;
};

export type DeletedTransactionResult = {
  meta: TransactionMutationMeta;
};
