export type DistributionFrequency = 'monthly' | 'semi_monthly';
export type DistributionRelationType = 'savings' | 'transfer';

export type DistributionRule = {
  id: string;
  name: string;
  frequency: DistributionFrequency;
  outcomesCount: number;
  totalAmount: number;
};

export type DistributionRuleFormValues = {
  name: string;
  frequency: DistributionFrequency;
};

export type DistributionRuleWritePayload = {
  name: string;
  frequency: DistributionFrequency;
};

export type DistributionRelation = {
  id: string;
  fixedIncomeId: string;
  name: string;
  amount: number;
  type: DistributionRelationType;
};

export type DistributionRelationFormValues = {
  name: string;
  amount: string;
  type: DistributionRelationType;
};

export type DistributionRelationWritePayload = {
  fixedIncomeId: string;
  name: string;
  amount: number;
  type: DistributionRelationType;
};
