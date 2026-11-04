import { describe, expect, it } from 'vitest';

import { dashboardQueryKeys } from './dashboardQueries';

describe('dashboard query keys', () => {
  it('uses stable keys for the dashboard aggregate data', () => {
    expect(dashboardQueryKeys.data()).toEqual(['dashboard', 'data']);
  });

  it('includes the selected date range in period summary keys', () => {
    expect(
      dashboardQueryKeys.periodSummary({
        startDate: '2026-06-01',
        endDate: '2026-06-30',
      }),
    ).toEqual([
      'dashboard',
      'period-summary',
      {
        startDate: '2026-06-01',
        endDate: '2026-06-30',
      },
    ]);
  });
});
