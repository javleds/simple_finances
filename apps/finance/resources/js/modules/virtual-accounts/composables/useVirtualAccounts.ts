import { computed, ref } from 'vue';

import { resolveApiErrorMessage } from '@/modules/shared/lib/apiErrors';

import { createVirtualAccountsRepository } from '../repositories/virtualAccountsRepository';
import type {
  VirtualAccountItem,
  VirtualAccountsDashboard,
  VirtualAccountSnapshot,
  VirtualAccountSnapshotWritePayload,
} from '../types';

const repository = createVirtualAccountsRepository();

const emptyDashboard: VirtualAccountsDashboard = {
  summary: {
    currentBalance: 0,
    initialBalance: 0,
    manualContributions: 0,
    manualWithdrawals: 0,
    netCapital: 0,
    observedYield: 0,
    accountsCount: 0,
  },
  accounts: [],
};

export function useVirtualAccounts() {
  const dashboard = ref<VirtualAccountsDashboard>(emptyDashboard);
  const snapshotsByAccount = ref<Record<string, VirtualAccountSnapshot[]>>({});
  const selectedAccountId = ref<string | null>(null);
  const isLoading = ref(false);
  const isLoadingSnapshots = ref(false);
  const isSaving = ref(false);
  const loadError = ref<string | null>(null);
  const saveError = ref<string | null>(null);

  const selectedAccount = computed<VirtualAccountItem | null>(() => {
    if (!selectedAccountId.value) {
      return dashboard.value.accounts[0] ?? null;
    }

    return (
      dashboard.value.accounts.find((account) => account.accountId === selectedAccountId.value) ??
      null
    );
  });

  const selectedSnapshots = computed(() => {
    const accountId = selectedAccount.value?.accountId;

    if (!accountId) {
      return [];
    }

    return snapshotsByAccount.value[accountId] ?? [];
  });

  async function loadDashboard(): Promise<void> {
    isLoading.value = true;
    loadError.value = null;

    try {
      dashboard.value = await repository.loadDashboard();
      selectedAccountId.value = selectedAccount.value?.accountId ?? null;
    } catch (error) {
      loadError.value = resolveApiErrorMessage(error, 'No fue posible cargar cuentas virtuales.');
    } finally {
      isLoading.value = false;
    }
  }

  async function loadSnapshots(accountId: string): Promise<void> {
    isLoadingSnapshots.value = true;
    loadError.value = null;

    try {
      snapshotsByAccount.value = {
        ...snapshotsByAccount.value,
        [accountId]: await repository.listSnapshots(accountId),
      };
    } catch (error) {
      loadError.value = resolveApiErrorMessage(error, 'No fue posible cargar el historial.');
    } finally {
      isLoadingSnapshots.value = false;
    }
  }

  async function captureSnapshot(payload: VirtualAccountSnapshotWritePayload): Promise<boolean> {
    const accountId = selectedAccount.value?.accountId;

    if (!accountId) {
      return false;
    }

    isSaving.value = true;
    saveError.value = null;

    try {
      await repository.createSnapshot(accountId, payload);
      await loadDashboard();
      await loadSnapshots(accountId);
      selectedAccountId.value = accountId;

      return true;
    } catch (error) {
      saveError.value = resolveApiErrorMessage(error, 'No fue posible capturar el saldo.');
      return false;
    } finally {
      isSaving.value = false;
    }
  }

  return {
    dashboard,
    selectedAccount,
    selectedAccountId,
    selectedSnapshots,
    isLoading,
    isLoadingSnapshots,
    isSaving,
    loadError,
    saveError,
    loadDashboard,
    loadSnapshots,
    captureSnapshot,
  };
}
