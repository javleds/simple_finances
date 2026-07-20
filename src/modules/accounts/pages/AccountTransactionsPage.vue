<script setup lang="ts">
import { computed, ref, watch } from 'vue';

import type {
  Account,
  AccountMemberTransferResult,
  AccountPendingReimbursement,
} from '@/modules/accounts/types';
import AccountReimbursementsPanel from '@/modules/accounts/components/AccountReimbursementsPanel.vue';
import AccountTransactionsActivity from '@/modules/accounts/components/AccountTransactionsActivity.vue';
import AccountTransactionsFilters from '@/modules/accounts/components/AccountTransactionsFilters.vue';
import AccountTransactionsHeader from '@/modules/accounts/components/AccountTransactionsHeader.vue';
import AccountTransactionsModals from '@/modules/accounts/components/AccountTransactionsModals.vue';
import { useAccountTransactionActions } from '@/modules/accounts/composables/useAccountTransactionActions';
import { useAccountTransactionFilters } from '@/modules/accounts/composables/useAccountTransactionFilters';
import { useAccountTransactionListLoader } from '@/modules/accounts/composables/useAccountTransactionListLoader';
import { useAccountTransactionModalActions } from '@/modules/accounts/composables/useAccountTransactionModalActions';
import { useAccountTransactionModals } from '@/modules/accounts/composables/useAccountTransactionModals';
import { useAccountTransactionsContext } from '@/modules/accounts/composables/useAccountTransactionsContext';
import { useAccountGoalsCrud } from '@/modules/accounts/composables/useAccountGoalsCrud';
import { useAccountMemberTransfers } from '@/modules/accounts/composables/useAccountMemberTransfers';
import { useTransactionsCrud } from '@/modules/transactions/composables/useTransactionsCrud';
import type { TransactionMutationMeta } from '@/modules/transactions/types';

const props = defineProps<{ account?: Account }>();

const { activeFilters, clearFilters, searchTerm, selectedTypes, toggleType } =
  useAccountTransactionFilters();

const account = computed(() => props.account);
const reimbursementAccounts = computed(() => (account.value ? [account.value] : []));
const accountBalance = ref(account.value?.balance ?? 0);
const { accountId, accountUsers, currentUserId } = useAccountTransactionsContext(account);
const {
  goals: financialGoals,
  isLoading: isLoadingFinancialGoals,
  loadGoals,
} = useAccountGoalsCrud();

const {
  transactions,
  hasTransactions,
  hasMoreTransactions,
  hasReachedEnd,
  isLoading,
  isLoadingMore,
  isSaving,
  isDeleting,
  loadError,
  saveError,
  deleteError,
  listMeta,
  clearSaveError,
  clearDeleteError,
  loadTransactions,
  loadMoreTransactions,
  createTransaction,
  updateTransaction,
  deleteTransaction,
} = useTransactionsCrud();

const { handleLoadMoreRetry, infiniteStatusLabel, loadMoreSentinel, reloadTransactions } =
  useAccountTransactionListLoader({
    accountId,
    activeFilters,
    hasMoreTransactions,
    hasReachedEnd,
    isLoading,
    isLoadingMore,
    loadMoreTransactions,
    loadTransactions,
  });

const {
  closeCreateTransactionModal,
  closeDeleteTransactionModal,
  closeEditTransactionModal,
  closeFilters,
  createInitialValues,
  createFormState,
  editFormState,
  handleCreateFormStateChange,
  handleEditFormStateChange,
  isCreateTransactionModalOpen,
  isDeleteTransactionModalOpen,
  isEditTransactionModalOpen,
  isFiltersOpen,
  openCreateTransactionModal,
  openDeleteTransaction,
  openEditTransaction,
  openFilters,
  prepareNextCreateTransaction,
  selectedTransaction,
} = useAccountTransactionModals({
  clearDeleteError,
  clearSaveError,
  loadGoals: () => {
    if (accountId.value) {
      void loadGoals(accountId.value);
    }
  },
  transactions,
});

const { createTransactionActions, deleteTransactionActions, editTransactionActions } =
  useAccountTransactionModalActions({
    createFormState,
    editFormState,
    isDeleting,
    isSaving,
    selectedTransaction,
  });

const { confirmDeleteTransaction, handleEditTransactionSubmit, handleTransactionSubmit } =
  useAccountTransactionActions({
    accountId,
    closeCreateTransactionModal,
    closeDeleteTransactionModal,
    closeEditTransactionModal,
    createTransaction,
    deleteTransaction,
    onMutationMeta: applyMutationMeta,
    prepareNextCreateTransaction,
    selectedTransaction,
    updateTransaction,
  });

const {
  activeTransferKey,
  isTransferring,
  settleAllAccountReimbursements,
  settleAccountReimbursements,
  settleReimbursement,
  transferError,
} = useAccountMemberTransfers();

watch(
  () => account.value?.balance,
  (nextBalance) => {
    if (typeof nextBalance === 'number') {
      accountBalance.value = nextBalance;
    }
  },
  { immediate: true },
);

watch(listMeta, (nextMeta) => {
  if (!nextMeta) {
    return;
  }

  applyMutationMeta(nextMeta);
});

function applyMutationMeta(meta: TransactionMutationMeta): void {
  if (typeof meta.accountBalance === 'number') {
    accountBalance.value = meta.accountBalance;
  }
}

function applyTransferResults(results: AccountMemberTransferResult[]): void {
  const latestResult = results[results.length - 1];

  if (typeof latestResult?.accountBalance === 'number') {
    accountBalance.value = latestResult.accountBalance;
  }
}

async function handleSettleReimbursement(
  accountId: string,
  reimbursement: AccountPendingReimbursement,
): Promise<void> {
  const result = await settleReimbursement(accountId, reimbursement);

  if (result) {
    applyTransferResults([result]);
  }

  await reloadTransactions();
}

async function handleSettleAccountReimbursements(
  accountId: string,
  reimbursements: AccountPendingReimbursement[],
): Promise<void> {
  applyTransferResults(await settleAccountReimbursements(accountId, reimbursements));
  await reloadTransactions();
}

async function handleSettleAllAccountReimbursements(
  accounts: Array<{ id: string; pendingReimbursements: AccountPendingReimbursement[] }>,
): Promise<void> {
  applyTransferResults(await settleAllAccountReimbursements(accounts));
  await reloadTransactions();
}
</script>

<template>
  <section class="space-y-4">
    <AccountTransactionsHeader
      :balance="accountBalance"
    />

    <section v-if="transferError" class="rounded-2xl border border-(--app-color-danger) px-4 py-3">
      <p class="text-sm font-medium text-(--app-color-danger)">{{ transferError }}</p>
    </section>

    <AccountReimbursementsPanel
      :accounts="reimbursementAccounts"
      :active-transfer-key="activeTransferKey"
      :current-user-id="currentUserId"
      :is-transferring="isTransferring"
      title="Reembolsos pendientes"
      @settle="handleSettleReimbursement"
      @settle-all="handleSettleAllAccountReimbursements"
      @settle-account="handleSettleAccountReimbursements"
    />

    <AccountTransactionsActivity
      v-model:search-term="searchTerm"
      :current-user-id="currentUserId"
      :has-transactions="hasTransactions"
      :infinite-status-label="infiniteStatusLabel()"
      :is-loading="isLoading"
      :load-error="loadError"
      :show-load-more-retry="Boolean(loadError && hasTransactions)"
      :transactions="transactions"
      @create="openCreateTransactionModal"
      @delete="openDeleteTransaction"
      @edit="openEditTransaction"
      @load-more-retry="handleLoadMoreRetry"
      @open-filters="openFilters"
      @retry="reloadTransactions"
    >
      <template #loadMoreSentinel>
        <div ref="loadMoreSentinel" class="h-1 w-full" aria-hidden="true" />
      </template>
    </AccountTransactionsActivity>

    <AccountTransactionsFilters
      :open="isFiltersOpen"
      :selected-types="selectedTypes"
      @clear="clearFilters"
      @close="closeFilters"
      @toggle-type="toggleType"
    />

    <AccountTransactionsModals
      :account-id="accountId"
      :account-users="accountUsers"
      :create-initial-values="createInitialValues"
      :create-transaction-actions="createTransactionActions"
      :delete-error="deleteError"
      :delete-transaction-actions="deleteTransactionActions"
      :edit-transaction-actions="editTransactionActions"
      :financial-goals="financialGoals"
      :is-create-transaction-modal-open="isCreateTransactionModalOpen"
      :is-delete-transaction-modal-open="isDeleteTransactionModalOpen"
      :is-edit-transaction-modal-open="isEditTransactionModalOpen"
      :is-loading-financial-goals="isLoadingFinancialGoals"
      :save-error="saveError"
      :selected-transaction="selectedTransaction"
      @close-create-transaction="closeCreateTransactionModal"
      @close-delete-transaction="closeDeleteTransactionModal"
      @close-edit-transaction="closeEditTransactionModal"
      @confirm-delete-transaction="confirmDeleteTransaction"
      @create-form-state-change="handleCreateFormStateChange"
      @edit-form-state-change="handleEditFormStateChange"
      @edit-submit="handleEditTransactionSubmit"
      @submit="handleTransactionSubmit"
    />
  </section>
</template>
