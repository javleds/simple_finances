import type { AccountListFilters } from '@/modules/accounts/types';

export const accountQueryKeys = {
  all: ['accounts'] as const,
  list(filters: AccountListFilters | undefined, perPage: number | null = null) {
    return [
      ...this.all,
      'list',
      {
        kind: filters?.kind ?? [],
        perPage,
        search: filters?.search ?? '',
        status: filters?.status ?? [],
        surface: filters?.surface ?? [],
      },
    ] as const;
  },
  detail(accountId: string) {
    return [...this.all, 'detail', accountId] as const;
  },
};
