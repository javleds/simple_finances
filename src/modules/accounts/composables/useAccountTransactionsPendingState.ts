import { computed, ref, watch, type ComputedRef } from 'vue';

import { createDashboardRepository } from '@/modules/admin/repositories/dashboardRepository';
import type { Account, AccountPendingByUser } from '@/modules/accounts/types';
import type { TransactionMutationMeta } from '@/modules/transactions/types';

type UseAccountTransactionsPendingStateOptions = {
  account: ComputedRef<Account | undefined>;
  markTransactionsCompleted: (transactionIds: string[]) => void;
  reloadTransactions: () => Promise<void>;
};

const dashboardRepository = createDashboardRepository();

export function useAccountTransactionsPendingState(
  options: UseAccountTransactionsPendingStateOptions,
) {
  const isCompletePendingByUserModalOpen = ref(false);
  const selectedPendingByUserId = ref<string | null>(null);
  const isCompletingPendingByUser = ref(false);
  const completePendingByUserError = ref<string | null>(null);
  const completedPendingTransactionIds = new Set<string>();
  const completedPendingUserIds = ref<string[]>([]);
  const completedPendingBalanceAdjustment = ref(0);
  const pendingByUser = ref<AccountPendingByUser[]>([]);
  const accountBalance = ref(options.account.value?.balance ?? 0);

  const usersWithPendingExpenses = computed(() =>
    pendingByUser.value.filter(
      (user) =>
        user.amount > 0 &&
        user.transactionIds.length > 0 &&
        !completedPendingUserIds.value.includes(user.userId),
    ),
  );

  const selectedPendingByUser = computed(
    () =>
      usersWithPendingExpenses.value.find(
        (user) => user.userId === selectedPendingByUserId.value,
      ) ?? null,
  );

  watch(
    () => options.account.value?.balance,
    (nextBalance) => {
      if (typeof nextBalance !== 'number') {
        return;
      }

      syncAccountBalance(nextBalance);
    },
    { immediate: true },
  );

  watch(
    () => options.account.value,
    (nextAccount) => {
      if (!nextAccount) {
        pendingByUser.value = [];
        return;
      }

      pendingByUser.value = normalizePendingByUser(nextAccount.pendingByUser);
    },
    { immediate: true, deep: true },
  );

  function applyMutationMeta(meta: TransactionMutationMeta): void {
    if (typeof meta.accountBalance === 'number') {
      syncAccountBalance(meta.accountBalance);
    } else if (typeof meta.previousAccountBalance === 'number') {
      syncAccountBalance(meta.previousAccountBalance);
    }

    if (Array.isArray(meta.pendingByUser)) {
      pendingByUser.value = normalizePendingByUser(meta.pendingByUser);
    }
  }

  function openCompletePendingByUser(userId: string): void {
    completePendingByUserError.value = null;
    selectedPendingByUserId.value = userId;
    isCompletePendingByUserModalOpen.value = true;
  }

  function closeCompletePendingByUserModal(): void {
    isCompletePendingByUserModalOpen.value = false;
    selectedPendingByUserId.value = null;
    completePendingByUserError.value = null;
  }

  async function confirmCompletePendingByUser(): Promise<void> {
    const selectedUser = selectedPendingByUser.value;

    if (!selectedUser || selectedUser.transactionIds.length === 0) {
      return;
    }

    const pendingAmount = selectedUser.amount;
    const pendingTransactionIds = [...selectedUser.transactionIds];

    isCompletingPendingByUser.value = true;
    completePendingByUserError.value = null;

    try {
      const result = await dashboardRepository.completePendingTransactions(pendingTransactionIds);
      const completedIds =
        result.transactionIds.length > 0 ? result.transactionIds : pendingTransactionIds;
      const failedIds = new Set(result.failed.map((item) => item.id));
      const removableIds = completedIds.filter((transactionId) => !failedIds.has(transactionId));
      const completedAllUserPending = result.failed.length === 0;

      if (result.failed.length > 0) {
        completePendingByUserError.value = result.failed.map((item) => item.message).join(' ');
      }

      if (completedAllUserPending) {
        applyCompletedPendingBalance(pendingAmount);
        rememberCompletedPendingUser(selectedUser.userId);
      }

      rememberCompletedPendingTransactions(removableIds);
      markCompletedPendingByUser(selectedUser.userId, removableIds);
      options.markTransactionsCompleted(removableIds);
      await options.reloadTransactions();

      if (completedAllUserPending) {
        closeCompletePendingByUserModal();
      }
    } catch (error) {
      completePendingByUserError.value = resolveErrorMessage(
        error,
        'No fue posible completar los pendientes del usuario.',
      );
    } finally {
      isCompletingPendingByUser.value = false;
    }
  }

  function rememberCompletedPendingTransactions(transactionIds: string[]): void {
    for (const transactionId of transactionIds) {
      completedPendingTransactionIds.add(transactionId);
    }
  }

  function rememberCompletedPendingUser(userId: string): void {
    if (completedPendingUserIds.value.includes(userId)) {
      return;
    }

    completedPendingUserIds.value = [...completedPendingUserIds.value, userId];
  }

  function applyCompletedPendingBalance(amount: number): void {
    completedPendingBalanceAdjustment.value += amount;
    accountBalance.value += amount;
  }

  function syncAccountBalance(nextBalance: number): void {
    if (completedPendingBalanceAdjustment.value === 0) {
      accountBalance.value = nextBalance;
      return;
    }

    if (nextBalance >= accountBalance.value) {
      completedPendingBalanceAdjustment.value = 0;
      accountBalance.value = nextBalance;
      return;
    }

    accountBalance.value = nextBalance + completedPendingBalanceAdjustment.value;
  }

  function normalizePendingByUser(users: AccountPendingByUser[]): AccountPendingByUser[] {
    const hiddenUserIds = new Set(completedPendingUserIds.value);
    const visibleUsers = users.filter((user) => !hiddenUserIds.has(user.userId));

    if (completedPendingTransactionIds.size === 0) {
      return visibleUsers.filter((user) => user.amount > 0 && user.transactionIds.length > 0);
    }

    return visibleUsers
      .map((user) => {
        const transactionIds = user.transactionIds.filter(
          (transactionId) => !completedPendingTransactionIds.has(transactionId),
        );

        return {
          ...user,
          amount: transactionIds.length === 0 ? 0 : user.amount,
          transactionIds,
        };
      })
      .filter((user) => user.amount > 0 && user.transactionIds.length > 0);
  }

  function markCompletedPendingByUser(userId: string, transactionIds: string[]): void {
    if (transactionIds.length === 0) {
      return;
    }

    const completedIds = new Set(transactionIds);

    pendingByUser.value = pendingByUser.value
      .map((user) => {
        if (user.userId !== userId) {
          return user;
        }

        const remainingTransactionIds = user.transactionIds.filter(
          (transactionId) => !completedIds.has(transactionId),
        );

        if (remainingTransactionIds.length === 0) {
          return {
            ...user,
            amount: 0,
            transactionIds: [],
          };
        }

        return {
          ...user,
          transactionIds: remainingTransactionIds,
        };
      })
      .filter((user) => user.amount > 0 && user.transactionIds.length > 0);
  }

  return {
    accountBalance,
    applyMutationMeta,
    closeCompletePendingByUserModal,
    completePendingByUserError,
    confirmCompletePendingByUser,
    isCompletePendingByUserModalOpen,
    isCompletingPendingByUser,
    openCompletePendingByUser,
    selectedPendingByUser,
    usersWithPendingExpenses,
  };
}

function resolveErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof Error && error.message) {
    return error.message;
  }

  return fallback;
}
