import { computed, ref, nextTick, type Ref } from 'vue';
import { describe, expect, it, vi } from 'vitest';

import { useAccountTransactionsPendingState } from '@/modules/accounts/composables/useAccountTransactionsPendingState';
import type { Account } from '@/modules/accounts/types';
import type { BatchTransactionsResult } from '@/modules/admin/types/dashboard';

function createAccount(overrides: Partial<Account> = {}): Account {
  return {
    id: 'account-1',
    name: 'Shared account',
    description: '',
    color: null,
    isVirtual: false,
    isCredit: false,
    status: 'Activo',
    balance: 100,
    totalSpent: 0,
    availableCredit: null,
    creditLine: null,
    closingDay: null,
    fundingAccountId: null,
    users: [],
    pendingByUser: [
      {
        userId: 'user-1',
        userName: 'Active User',
        amount: 30,
        transactionIds: ['tx-1', 'tx-2'],
      },
      {
        userId: 'user-2',
        userName: 'Other User',
        amount: 15,
        transactionIds: ['tx-3'],
      },
    ],
    ...overrides,
  };
}

function createPendingState(options: {
  account?: Ref<Account | undefined>;
  completeResult: BatchTransactionsResult;
}) {
  const markTransactionsCompleted = vi.fn<(transactionIds: string[]) => void>();
  const reloadTransactions = vi.fn<() => Promise<void>>().mockResolvedValue(undefined);
  const completePendingTransactions =
    vi.fn<(transactionIds: string[]) => Promise<BatchTransactionsResult>>().mockResolvedValue(
      options.completeResult,
    );
  const account = options.account ?? ref<Account | undefined>(createAccount());
  const state = useAccountTransactionsPendingState({
    account: computed(() => account.value),
    dashboardRepository: { completePendingTransactions },
    markTransactionsCompleted,
    reloadTransactions,
  });

  return {
    account,
    completePendingTransactions,
    markTransactionsCompleted,
    reloadTransactions,
    state,
  };
}

describe('useAccountTransactionsPendingState', () => {
  it('hides a user and adjusts account balance when all pending transactions complete', async () => {
    const { completePendingTransactions, markTransactionsCompleted, reloadTransactions, state } =
      createPendingState({
        completeResult: {
          processed: 2,
          failed: [],
          transactionIds: ['tx-1', 'tx-2'],
        },
      });

    state.openCompletePendingByUser('user-1');
    await state.confirmCompletePendingByUser();

    expect(completePendingTransactions).toHaveBeenCalledWith(['tx-1', 'tx-2']);
    expect(markTransactionsCompleted).toHaveBeenCalledWith(['tx-1', 'tx-2']);
    expect(reloadTransactions).toHaveBeenCalledOnce();
    expect(state.accountBalance.value).toBe(130);
    expect(state.usersWithPendingExpenses.value.map((user) => user.userId)).toEqual(['user-2']);
    expect(state.isCompletePendingByUserModalOpen.value).toBe(false);
    expect(state.completePendingByUserError.value).toBeNull();
  });

  it('keeps the user visible and reports failures when completion is partial', async () => {
    const { markTransactionsCompleted, state } = createPendingState({
      completeResult: {
        processed: 1,
        failed: [{ id: 'tx-2', message: 'No se pudo completar tx-2.' }],
        transactionIds: ['tx-1', 'tx-2'],
      },
    });

    state.openCompletePendingByUser('user-1');
    await state.confirmCompletePendingByUser();

    const activeUser = state.usersWithPendingExpenses.value.find(
      (user) => user.userId === 'user-1',
    );

    expect(markTransactionsCompleted).toHaveBeenCalledWith(['tx-1']);
    expect(state.accountBalance.value).toBe(100);
    expect(activeUser?.transactionIds).toEqual(['tx-2']);
    expect(state.isCompletePendingByUserModalOpen.value).toBe(true);
    expect(state.completePendingByUserError.value).toBe('No se pudo completar tx-2.');
  });

  it('does not reintroduce a completed user when fresh account pending data still includes it', async () => {
    const account = ref<Account | undefined>(createAccount());
    const { state } = createPendingState({
      account,
      completeResult: {
        processed: 2,
        failed: [],
        transactionIds: ['tx-1', 'tx-2'],
      },
    });

    state.openCompletePendingByUser('user-1');
    await state.confirmCompletePendingByUser();

    account.value = createAccount({ balance: 100 });
    await nextTick();

    expect(state.usersWithPendingExpenses.value.map((user) => user.userId)).toEqual(['user-2']);
    expect(state.accountBalance.value).toBe(130);
  });
});
