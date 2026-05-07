export type TransactionType = 'income' | 'expense';
export type TransactionStatus = 'pending' | 'completed';

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
