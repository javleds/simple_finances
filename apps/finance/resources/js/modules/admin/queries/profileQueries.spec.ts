import { describe, expect, it } from 'vitest';

import { profileQueryKeys } from './profileQueries';

describe('profile query keys', () => {
  it('uses stable keys for the current profile', () => {
    expect(profileQueryKeys.detail()).toEqual(['profile', 'detail']);
  });
});
