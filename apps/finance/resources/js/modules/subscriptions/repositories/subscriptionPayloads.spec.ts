import { describe, expect, it } from 'vitest';

import {
  buildSubscriptionWritePayload,
  mapSubscriptionStatusesToFinishedFilter,
} from './subscriptionPayloads';

describe('subscription payload builders', () => {
  it('maps frontend subscription form values to the API write contract', () => {
    expect(
      buildSubscriptionWritePayload({
        name: 'Cloud storage',
        amount: 199,
        startDate: '2026-06-01',
        frequencyUnit: 1,
        frequencyType: 'months',
        finishedAt: null,
        fundingAccountId: '15',
      }),
    ).toEqual({
      name: 'Cloud storage',
      amount: 199,
      started_at: '2026-06-01',
      frequency_unit: 1,
      frequency_type: 'months',
      finished_at: null,
      feed_account_id: '15',
    });
  });

  it('maps single status filters to the API finished flag', () => {
    expect(mapSubscriptionStatusesToFinishedFilter(['active'])).toBe(0);
    expect(mapSubscriptionStatusesToFinishedFilter(['cancelled'])).toBe(1);
    expect(mapSubscriptionStatusesToFinishedFilter(['active', 'cancelled'])).toBeUndefined();
    expect(mapSubscriptionStatusesToFinishedFilter(undefined)).toBeUndefined();
  });
});
