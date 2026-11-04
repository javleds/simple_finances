import type { TransactionFacilityFilters } from '@/modules/transactions/types';

export const transactionFacilityQueryKeys = {
  all: ['transactions', 'facility'] as const,
  list(filters: TransactionFacilityFilters, perPage: number) {
    return [
      ...this.all,
      {
        endDate: filters.endDate,
        categoryIds: filters.categoryIds ?? [],
        uncategorized: filters.uncategorized ?? false,
        hasAttachments: filters.hasAttachments ?? null,
        perPage,
        search: filters.search ?? '',
        startDate: filters.startDate,
      },
    ] as const;
  },
};
