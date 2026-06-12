import { computed, ref, type Ref } from 'vue';

import type {
  DashboardData,
  DashboardPendingAction,
  DashboardPendingActionGroup,
} from '@/modules/admin/types/dashboard';

type UseDashboardPendingActionsOptions = {
  clearCompleteError: () => void;
  completePendingTransactions: (transactionIds: string[]) => Promise<boolean>;
  dashboard: Ref<DashboardData>;
};

export function useDashboardPendingActions(options: UseDashboardPendingActionsOptions) {
  const isCompletePendingActionOpen = ref(false);
  const selectedPendingActionId = ref<string | null>(null);
  const selectedPendingAccountId = ref<string | null>(null);

  const pendingActions = computed<DashboardPendingAction[]>(() =>
    [...options.dashboard.value.pendingActions].sort(
      (left, right) => parseDate(right.date) - parseDate(left.date),
    ),
  );

  const pendingActionGroups = computed<DashboardPendingActionGroup[]>(() => {
    const groups = new Map<string, DashboardPendingActionGroup>();

    for (const action of pendingActions.value) {
      const group = groups.get(action.accountId);

      if (group) {
        group.items.push(action);
        group.totalAmount += action.amount;
        continue;
      }

      groups.set(action.accountId, {
        accountId: action.accountId,
        accountName: action.accountName,
        accountColor: action.accountColor,
        totalAmount: action.amount,
        items: [action],
      });
    }

    return [...groups.values()].sort(
      (left, right) => parseDate(right.items[0]?.date) - parseDate(left.items[0]?.date),
    );
  });

  const selectedPendingAction = computed(() => {
    if (!selectedPendingActionId.value) {
      return null;
    }

    return pendingActions.value.find((item) => item.id === selectedPendingActionId.value) ?? null;
  });

  const selectedPendingAccountActions = computed(() => {
    if (!selectedPendingAccountId.value) {
      return [];
    }

    return pendingActions.value.filter((item) => item.accountId === selectedPendingAccountId.value);
  });

  function openCompletePendingAction(actionId: string): void {
    options.clearCompleteError();
    selectedPendingActionId.value = actionId;
    selectedPendingAccountId.value = null;
    isCompletePendingActionOpen.value = true;
  }

  function openCompletePendingAccount(accountId: string): void {
    options.clearCompleteError();
    selectedPendingActionId.value = null;
    selectedPendingAccountId.value = accountId;
    isCompletePendingActionOpen.value = true;
  }

  function closeCompletePendingAction(): void {
    isCompletePendingActionOpen.value = false;
    selectedPendingActionId.value = null;
    selectedPendingAccountId.value = null;
  }

  async function confirmCompletePendingAction(): Promise<void> {
    const transactionIds = selectedPendingAction.value
      ? [selectedPendingAction.value.id]
      : selectedPendingAccountActions.value.map((action) => action.id);

    const wasCompleted = await options.completePendingTransactions(transactionIds);

    if (wasCompleted) {
      closeCompletePendingAction();
    }
  }

  return {
    closeCompletePendingAction,
    confirmCompletePendingAction,
    isCompletePendingActionOpen,
    openCompletePendingAccount,
    openCompletePendingAction,
    pendingActionGroups,
    selectedPendingAccountActions,
    selectedPendingAction,
  };
}

function parseDate(value?: string): number {
  if (!value) {
    return 0;
  }

  return Date.parse(value);
}
