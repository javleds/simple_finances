<script setup lang="ts">
import { computed, watch } from 'vue';
import { useRoute } from 'vue-router';

import { getStoredAuthSession } from '@/modules/auth/lib/authSession';
import type { Account } from '@/modules/accounts/types';
import AccountCompletePendingByUserModal from '@/modules/accounts/components/AccountCompletePendingByUserModal.vue';
import AccountTransactionCompleteModal from '@/modules/accounts/components/AccountTransactionCompleteModal.vue';
import AccountTransactionDeleteModal from '@/modules/accounts/components/AccountTransactionDeleteModal.vue';
import AccountTransactionFiltersModal from '@/modules/accounts/components/AccountTransactionFiltersModal.vue';
import AccountTransactionFormModal from '@/modules/accounts/components/AccountTransactionFormModal.vue';
import AccountTransactionsHeader from '@/modules/accounts/components/AccountTransactionsHeader.vue';
import AccountTransactionsList from '@/modules/accounts/components/AccountTransactionsList.vue';
import AccountTransactionsToolbar from '@/modules/accounts/components/AccountTransactionsToolbar.vue';
import { useAccountTransactionActions } from '@/modules/accounts/composables/useAccountTransactionActions';
import { useAccountTransactionFilters } from '@/modules/accounts/composables/useAccountTransactionFilters';
import { useAccountTransactionModalActions } from '@/modules/accounts/composables/useAccountTransactionModalActions';
import { useAccountTransactionModals } from '@/modules/accounts/composables/useAccountTransactionModals';
import { useAccountTransactionsPendingState } from '@/modules/accounts/composables/useAccountTransactionsPendingState';
import { useAccountGoalsCrud } from '@/modules/accounts/composables/useAccountGoalsCrud';
import {
  AppButton,
  AppListState,
  AppLoadMoreFooter,
  AppText,
} from '@/modules/shared/components';
import { useInfiniteScroll } from '@/modules/shared/composables/useInfiniteScroll';
import { useTransactionsCrud } from '@/modules/transactions/composables/useTransactionsCrud';
import type { TransactionStatus } from '@/modules/transactions/types';

const props = defineProps<{
  account?: Account;
}>();

const route = useRoute();

const transactionStatusOptions = [
  { value: 'completed', label: 'Completado' },
  { value: 'pending', label: 'Pendiente' },
] as const;

const transactionTypeOptions = [
  { value: 'income', label: 'Ingreso' },
  { value: 'expense', label: 'Egreso' },
] as const;
const defaultTransactionsPerPage = 20;
const currentUserId = computed(() => getStoredAuthSession()?.user.id ?? null);
const {
  activeFilters,
  clearFilters,
  searchTerm,
  selectedStatuses,
  selectedTypes,
  toggleStatus,
  toggleType,
} = useAccountTransactionFilters();

const accountId = computed(() =>
  typeof route.params.accountId === 'string' ? route.params.accountId : '',
);
const account = computed(() => props.account);

const accountUsers = computed(() => props.account?.users ?? []);
const isSharedAccount = computed(() => accountUsers.value.length > 1);
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

const transactionsPerPage = computed(() => {
  const rawValue =
    typeof route.query.perPage === 'string' ? Number(route.query.perPage) : Number.NaN;

  if (!Number.isInteger(rawValue) || rawValue <= 0) {
    return defaultTransactionsPerPage;
  }

  return rawValue;
});

const { target: loadMoreSentinel } = useInfiniteScroll({
  enabled: computed(() => !isLoading.value && !isLoadingMore.value && hasMoreTransactions.value),
  onIntersect: () => {
    void loadMoreTransactions();
  },
});

const {
  closeCompleteTransactionModal,
  closeCreateTransactionModal,
  closeDeleteTransactionModal,
  closeEditTransactionModal,
  closeFilters,
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
  handleLoadMoreRetry,
  handleTransactionSubmit,
  infiniteStatusLabel,
} = useAccountTransactionActions({
  accountId,
  activeFilters,
  canCompleteTransaction,
  closeCompleteTransactionModal,
  closeCreateTransactionModal,
  closeDeleteTransactionModal,
  closeEditTransactionModal,
  createTransaction,
  deleteTransaction,
  hasReachedEnd,
  isLoadingMore,
  loadMoreTransactions,
  loadTransactions,
  onMutationMeta: applyMutationMeta,
  selectedTransaction,
  transactionsPerPage,
  updateTransaction,
});

watch(
  [accountId, activeFilters, transactionsPerPage],
  ([nextAccountId, nextFilters, nextPerPage]) => {
    if (!nextAccountId) {
      return;
    }

    void loadTransactions(nextAccountId, nextFilters, {
      reset: true,
      perPage: nextPerPage,
    });
  },
  { immediate: true },
);

watch(listMeta, (nextMeta) => {
  if (!nextMeta) {
    return;
  }

  applyMutationMeta(nextMeta);
});

function canManageTransaction(options: { creatorId: string | null }): boolean {
  return Boolean(currentUserId.value && options.creatorId === currentUserId.value);
}

function canCompleteTransaction(options: {
  creatorId: string | null;
  status: TransactionStatus | null;
}): boolean {
  return canManageTransaction(options) && options.status === 'pending';
}

async function reloadTransactions(): Promise<void> {
  if (!accountId.value) {
    return;
  }

  await loadTransactions(accountId.value, activeFilters.value, {
    reset: true,
    perPage: transactionsPerPage.value,
  });
}

</script>

<template>
  <section class="space-y-4">
    <AccountTransactionsHeader
      :balance="accountBalance"
      :current-user-id="currentUserId"
      :is-completing-pending-by-user="isCompletingPendingByUser"
      :is-shared-account="isSharedAccount"
      :pending-users="usersWithPendingExpenses"
      @complete-pending-user="openCompletePendingByUser"
    />

    <AccountTransactionsToolbar
      v-model:search-term="searchTerm"
      @create="openCreateTransactionModal"
      @open-filters="openFilters"
    />

    <section
      v-if="loadError && hasTransactions"
      class="rounded-2xl border border-(--app-color-danger) px-4 py-3"
    >
      <AppText class="text-(--app-color-danger)!">{{ loadError }}</AppText>
    </section>

    <AppListState
      :error="loadError"
      :has-items="hasTransactions"
      :is-loading="isLoading"
      loading-label="Cargando transacciones..."
      @retry="reloadTransactions"
    >
      <AccountTransactionsList
        :current-user-id="currentUserId"
        :transactions="transactions"
        @complete="openCompleteTransaction"
        @delete="openDeleteTransaction"
        @edit="openEditTransaction"
      >
        <template #footer>
          <div ref="loadMoreSentinel">
            <AppLoadMoreFooter
              :label="infiniteStatusLabel()"
              :show-retry="Boolean(loadError && hasTransactions)"
              @retry="handleLoadMoreRetry"
            />
          </div>
        </template>
      </AccountTransactionsList>
    </AppListState>

    <AccountTransactionFiltersModal
      :open="isFiltersOpen"
      :selected-statuses="selectedStatuses"
      :selected-types="selectedTypes"
      :status-options="transactionStatusOptions"
      :type-options="transactionTypeOptions"
      @clear="clearFilters"
      @close="closeFilters"
      @toggle-status="toggleStatus"
      @toggle-type="toggleType"
    />

    <AccountTransactionFormModal
      :open="isCreateTransactionModalOpen"
      :account-id="accountId"
      :account-users="accountUsers"
      :actions="createTransactionActions"
      :financial-goals="financialGoals"
      form-id="transaction-form"
      :is-loading-financial-goals="isLoadingFinancialGoals"
      :server-error="saveError"
      title="Nueva transacción"
      @close="closeCreateTransactionModal"
      @state-change="handleCreateFormStateChange"
      @submit="handleTransactionSubmit"
    />

    <AccountTransactionFormModal
      :open="isEditTransactionModalOpen"
      :account-id="accountId"
      :account-users="accountUsers"
      :actions="editTransactionActions"
      :financial-goals="financialGoals"
      form-id="edit-transaction-form"
      :initial-values="selectedTransaction"
      :is-loading-financial-goals="isLoadingFinancialGoals"
      requires-initial-values
      :server-error="saveError"
      title="Editar transacción"
      @close="closeEditTransactionModal"
      @state-change="handleEditFormStateChange"
      @submit="handleEditTransactionSubmit"
    />

    <AccountTransactionDeleteModal
      :open="isDeleteTransactionModalOpen"
      :actions="deleteTransactionActions"
      :delete-error="deleteError"
      :transaction="selectedTransaction"
      @close="closeDeleteTransactionModal"
      @confirm="confirmDeleteTransaction"
    />

    <AccountTransactionCompleteModal
      :open="isCompleteTransactionModalOpen"
      :actions="completeTransactionActions"
      :save-error="saveError"
      :transaction="selectedTransaction"
      @close="closeCompleteTransactionModal"
      @confirm="confirmCompleteTransaction"
    />

    <AccountCompletePendingByUserModal
      :open="isCompletePendingByUserModalOpen"
      :actions="completePendingByUserActions"
      :complete-error="completePendingByUserError"
      :pending-user="selectedPendingByUser"
      @close="closeCompletePendingByUserModal"
      @confirm="confirmCompletePendingByUser"
    />
  </section>
</template>
