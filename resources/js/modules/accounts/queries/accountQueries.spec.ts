import { describe, expect, it } from 'vitest';

import { accountQueryKeys } from './accountQueries';

describe('account query keys', () => {
  it('includes filters and page size in list keys', () => {
    expect(
      accountQueryKeys.list(
        {
          search: 'cash',
          status: ['Activo'],
          kind: ['debit'],
          surface: ['physical'],
        },
        20,
      ),
    ).toEqual([
      'accounts',
      'list',
      {
        kind: ['debit'],
        perPage: 20,
        search: 'cash',
        status: ['Activo'],
        surface: ['physical'],
      },
    ]);
  });

  it('normalizes missing filters so list keys stay stable', () => {
    expect(accountQueryKeys.list(undefined)).toEqual([
      'accounts',
      'list',
      {
        kind: [],
        perPage: null,
        search: '',
        status: [],
        surface: [],
      },
    ]);
  });

  it('uses the account id in detail keys', () => {
    expect(accountQueryKeys.detail('account-1')).toEqual(['accounts', 'detail', 'account-1']);
  });
});
