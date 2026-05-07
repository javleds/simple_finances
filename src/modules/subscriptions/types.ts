export type SubscriptionFrequencyUnit = 'day' | 'week' | 'month' | 'year';

export type Subscription = {
  id: string;
  name: string;
  amount: number;
  startDate: string;
  frequencyEvery: number;
  frequencyUnit: SubscriptionFrequencyUnit;
  cancellationDate: string | null;
  fundingAccountId: string | null;
  fundingAccountName: string | null;
  nextPaymentDate: string | null;
  previousPaymentDate: string | null;
};

export type SubscriptionFormValues = {
  name: string;
  amount: string;
  startDate: string;
  frequencyEvery: string;
  frequencyUnit: SubscriptionFrequencyUnit;
  cancellationDate: string;
  fundingAccountId: string | null;
};

export type SubscriptionWritePayload = {
  name: string;
  amount: number;
  startDate: string;
  frequencyEvery: number;
  frequencyUnit: SubscriptionFrequencyUnit;
  cancellationDate: string | null;
  fundingAccountId: string | null;
};
