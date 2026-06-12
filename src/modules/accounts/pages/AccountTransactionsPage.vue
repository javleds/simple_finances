<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';

import { getStoredAuthSession } from '@/modules/auth/lib/authSession';
import type { Account, AccountPendingByUser } from '@/modules/accounts/types';
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
import { useAccountGoalsCrud } from '@/modules/accounts/composables/useAccountGoalsCrud';
import { createDashboardRepository } from '@/modules/admin/repositories/dashboardRepository';
import {
  AppButton,
  AppListState,
  AppLoadMoreFooter,
  AppText,
} from '@/modules/shared/components';
import { useInfiniteScroll } from '@/modules/shared/composables/useInfiniteScroll';
import { useTransactionsCrud } from '@/modules/transactions/composables/useTransactionsCrud';
import type {
  TransactionMutationMeta,
  TransactionStatus,
  TransactionWritePayload,
} from '@/modules/transactions/types';

const props = defineProps<{
  account?: Account;
}>();

const route = useRoute();
const dashboardRepository = createDashboardRepository();

const isCompletePendingByUserModalOpen = ref(false);
const selectedPendingByUserId = ref<string | null>(null);
const isCompletingPendingByUser = ref(false);
const completePendingByUserError = ref<string | null>(null);
const completedPendingTransactionIds = new Set<string>();
const completedPendingUserIds = ref<string[]>([]);
const completedPendingBalanceAdjustment = ref(0);

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

const accountUsers = computed(() => props.account?.users ?? []);
const isSharedAccount = computed(() => accountUsers.value.length > 1);
const pendingByUser = ref<AccountPendingByUser[]>([]);
const selectedPendingByUser = computed(
  () =>
    usersWithPendingExpenses.value.find((user) => user.userId === selectedPendingByUserId.value) ??
    null,
);
const usersWithPendingExpenses = computed(() =>
  pendingByUser.value.filter(
    (user) =>
      user.amount > 0 &&
      user.transactionIds.length > 0 &&
      !completedPendingUserIds.value.includes(user.userId),
  ),
);
const accountBalance = ref(props.account?.balance ?? 0);
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
  reloadTransactions,
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
  () => props.account?.balance,
  (nextBalance) => {
    if (typeof nextBalance !== 'number') {
      return;
    }

    syncAccountBalance(nextBalance);
  },
  { immediate: true },
);

watch(
  () => props.account,
  (nextAccount) => {
    if (!nextAccount) {
      pendingByUser.value = [];
      return;
    }

    pendingByUser.value = normalizePendingByUser(nextAccount.pendingByUser);
  },
  { immediate: true, deep: true },
);

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

function resolveErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof Error && error.message) {
    return error.message;
  }

  return fallback;
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
    const result = await dashboardRepository.completePendingTransactions(
      pendingTransactionIds,
    );
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
    markTransactionsCompleted(removableIds);
    await reloadTransactions();

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

function canManageTransaction(options: { creatorId: string | null }): boolean {
  return Boolean(currentUserId.value && options.creatorId === currentUserId.value);
}

function canCompleteTransaction(options: {
  creatorId: string | null;
  status: TransactionStatus | null;
}): boolean {
  return canManageTransaction(options) && options.status === 'pending';
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
