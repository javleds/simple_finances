export type SubscriptionFrequencyType = 'days' | 'months' | 'years';
export type SubscriptionStatusFilter = 'active' | 'cancelled';
export type SubscriptionListFilters = {
  search?: string;
  status?: SubscriptionStatusFilter[];
  frequencyType?: SubscriptionFrequencyType[];
};

export type Subscription = {
  id: string;
  name: string;
  amount: number;
  startDate: string;
  frequencyUnit: number;
  frequencyType: SubscriptionFrequencyType;
  finishedAt: string | null;
  fundingAccountId: string | null;
  fundingAccountName: string | null;
  nextPaymentDate: string | null;
  previousPaymentDate: string | null;
};

export type SubscriptionFormValues = {
  name: string;
  amount: string;
  startDate: string;
  frequencyUnit: string;
  frequencyType: SubscriptionFrequencyType;
  finishedAt: string;
  fundingAccountId: string | null;
};

export type SubscriptionWritePayload = {
  name: string;
  amount: number;
  startDate: string;
  frequencyUnit: number;
  frequencyType: SubscriptionFrequencyType;
  finishedAt: string | null;
  fundingAccountId: string | null;
};
