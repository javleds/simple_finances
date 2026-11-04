import type { SubscriptionListFilters } from '@/modules/subscriptions/types';

export const subscriptionQueryKeys = {
  all: ['subscriptions'] as const,
  list(filters: SubscriptionListFilters | undefined, perPage: number) {
    return [
      ...this.all,
      'list',
      {
        frequencyType: filters?.frequencyType ?? [],
        perPage,
        search: filters?.search ?? '',
        status: filters?.status ?? [],
      },
    ] as const;
  },
};
