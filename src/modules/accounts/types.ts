export type AccountStatus = 'Activo' | 'Inactivo';

export type AccountMember = {
  id: string;
  name: string;
  email: string;
  allocationPercentage: number;
  pendingExpenses: number;
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
