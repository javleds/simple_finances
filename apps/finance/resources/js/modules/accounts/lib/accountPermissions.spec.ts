import { describe, expect, it } from 'vitest';

import type { Account } from '@/modules/accounts/types';

import { canDeleteAccount, canLeaveAccount } from './accountPermissions';

function account(ownerId: string): Account {
  return {
    id: 'account-1',
    ownerId,
    name: 'Shared',
    description: '',
    color: null,
    isVirtual: false,
    isCredit: false,
    status: 'Activo',
    balance: 0,
    totalSpent: 0,
    availableCredit: null,
    creditLine: null,
    closingDay: null,
    fundingAccountId: null,
    users: [],
    custodyByUser: [],
    settlementsByUser: [],
    pendingReimbursements: [],
  };
}

describe('account permissions', () => {
  it('lets only the owner delete an account', () => {
    expect(canDeleteAccount(account('1'), '1')).toBe(true);
    expect(canDeleteAccount(account('1'), '2')).toBe(false);
    expect(canDeleteAccount(account('1'), null)).toBe(false);
  });

  it('lets non owner members leave an account', () => {
    expect(canLeaveAccount(account('1'), '2')).toBe(true);
    expect(canLeaveAccount(account('1'), '1')).toBe(false);
    expect(canLeaveAccount(account('1'), null)).toBe(false);
  });
});
