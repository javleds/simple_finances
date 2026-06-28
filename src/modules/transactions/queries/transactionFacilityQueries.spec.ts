import { describe, expect, it } from 'vitest';

import { transactionFacilityQueryKeys } from './transactionFacilityQueries';

describe('transaction facility query keys', () => {
  it('includes the period, search term, and page size in the list key', () => {
    expect(
      transactionFacilityQueryKeys.list(
        {
          startDate: '2026-06-01',
          endDate: '2026-06-30',
          search: 'renta',
        },
        20,
      ),
    ).toEqual([
      'transactions',
      'facility',
      {
        endDate: '2026-06-30',
        perPage: 20,
        search: 'renta',
        startDate: '2026-06-01',
      },
    ]);
  });

  it('normalizes missing search terms so cache keys stay stable', () => {
    expect(
      transactionFacilityQueryKeys.list(
        {
          startDate: '2026-06-01',
          endDate: '2026-06-30',
        },
        20,
      ),
    ).toEqual([
      'transactions',
      'facility',
      {
        endDate: '2026-06-30',
        perPage: 20,
        search: '',
        startDate: '2026-06-01',
      },
    ]);
  });
});
