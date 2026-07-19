<script setup lang="ts">
import { computed, watch } from 'vue';

import type { Account } from '@/modules/accounts/types';
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
import { useAccountTransactionsPendingState } from '@/modules/accounts/composables/useAccountTransactionsPendingState';
import { useAccountGoalsCrud } from '@/modules/accounts/composables/useAccountGoalsCrud';
import { useTransactionsCrud } from '@/modules/transactions/composables/useTransactionsCrud';
import { canCompleteTransaction } from '@/modules/transactions/lib/transactionPermissions';

const props = defineProps<{ account?: Account }>();

const {
  activeFilters,
  clearFilters,
  searchTerm,
  selectedStatuses,
  selectedTypes,
  toggleStatus,
  toggleType,
} = useAccountTransactionFilters();

const account = computed(() => props.account);
const { accountId, accountUsers, currentUserId, isSharedAccount } =
  useAccountTransactionsContext(account);
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
  markTransactionsCompleted,
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
  closeCompleteTransactionModal,
  closeCreateTransactionModal,
  closeDeleteTransactionModal,
  closeEditTransactionModal,
  closeFilters,
  createInitialValues,
  createFormState,
  editFormState,
  handleCreateFormStateChange,
  handleEditFormStateChange,
  isCompleteTransactionModalOpen,
  isCreateTransactionModalOpen,
  isDeleteTransactionModalOpen,
  isEditTransactionModalOpen,
  isFiltersOpen,
  openCompleteTransaction,
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

const {
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
} = useAccountTransactionsPendingState({
  account,
  markTransactionsCompleted,
  reloadTransactions,
});

const {
  completePendingByUserActions,
  completeTransactionActions,
  createTransactionActions,
  deleteTransactionActions,
  editTransactionActions,
} = useAccountTransactionModalActions({
  createFormState,
  editFormState,
  isCompletingPendingByUser,
  isDeleting,
  isSaving,
  selectedPendingByUser,
  selectedTransaction,
});

const {
  confirmCompleteTransaction,
  confirmDeleteTransaction,
  handleEditTransactionSubmit,
  handleTransactionSubmit,
} = useAccountTransactionActions({
  accountId,
  canCompleteTransaction: (transaction) => canCompleteTransaction(transaction, currentUserId.value),
  closeCompleteTransactionModal,
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

watch(listMeta, (nextMeta) => {
  if (!nextMeta) {
    return;
  }

  applyMutationMeta(nextMeta);
});
</script>

<template>
  <section class="space-y-4">
    <AccountTransactionsHeader
      :balance="accountBalance"
      :current-user-id="currentUserId"
      :is-completing-pending-by-user="isCompletingPendingByUser"
      :is-shared-account="isSharedAccount"
      :pending-reimbursements="account?.pendingReimbursements ?? []"
      :pending-users="usersWithPendingExpenses"
      @complete-pending-user="openCompletePendingByUser"
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
      @complete="openCompleteTransaction"
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
      :selected-statuses="selectedStatuses"
      :selected-types="selectedTypes"
      @clear="clearFilters"
      @close="closeFilters"
      @toggle-status="toggleStatus"
      @toggle-type="toggleType"
    />

    <AccountTransactionsModals
      :account-id="accountId"
      :account-users="accountUsers"
      :complete-pending-by-user-actions="completePendingByUserActions"
      :complete-pending-by-user-error="completePendingByUserError"
      :complete-transaction-actions="completeTransactionActions"
      :create-initial-values="createInitialValues"
      :create-transaction-actions="createTransactionActions"
      :delete-error="deleteError"
      :delete-transaction-actions="deleteTransactionActions"
      :edit-transaction-actions="editTransactionActions"
      :financial-goals="financialGoals"
      :is-complete-pending-by-user-modal-open="isCompletePendingByUserModalOpen"
      :is-complete-transaction-modal-open="isCompleteTransactionModalOpen"
      :is-create-transaction-modal-open="isCreateTransactionModalOpen"
      :is-delete-transaction-modal-open="isDeleteTransactionModalOpen"
      :is-edit-transaction-modal-open="isEditTransactionModalOpen"
      :is-loading-financial-goals="isLoadingFinancialGoals"
      :save-error="saveError"
      :selected-pending-by-user="selectedPendingByUser"
      :selected-transaction="selectedTransaction"
      @close-complete-pending-by-user="closeCompletePendingByUserModal"
      @close-complete-transaction="closeCompleteTransactionModal"
      @close-create-transaction="closeCreateTransactionModal"
      @close-delete-transaction="closeDeleteTransactionModal"
      @close-edit-transaction="closeEditTransactionModal"
      @confirm-complete-pending-by-user="confirmCompletePendingByUser"
      @confirm-complete-transaction="confirmCompleteTransaction"
      @confirm-delete-transaction="confirmDeleteTransaction"
      @create-form-state-change="handleCreateFormStateChange"
      @edit-form-state-change="handleEditFormStateChange"
      @edit-submit="handleEditTransactionSubmit"
      @submit="handleTransactionSubmit"
    />
  </section>
</template>
