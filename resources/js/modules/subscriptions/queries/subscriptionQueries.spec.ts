import { describe, expect, it } from 'vitest';

import { subscriptionQueryKeys } from './subscriptionQueries';

describe('subscription query keys', () => {
  it('includes filters and page size in list keys', () => {
    expect(
      subscriptionQueryKeys.list(
        {
          search: 'cloud',
          status: ['active'],
          frequencyType: ['months', 'years'],
        },
        20,
      ),
    ).toEqual([
      'subscriptions',
      'list',
      {
        frequencyType: ['months', 'years'],
        perPage: 20,
        search: 'cloud',
        status: ['active'],
      },
    ]);
  });

  it('normalizes missing filters so list keys stay stable', () => {
    expect(subscriptionQueryKeys.list(undefined, 20)).toEqual([
      'subscriptions',
      'list',
      {
        frequencyType: [],
        perPage: 20,
        search: '',
        status: [],
      },
    ]);
  });
});
