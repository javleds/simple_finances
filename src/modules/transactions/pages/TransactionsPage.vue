<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';

import TransactionFacilityActivity from '@/modules/transactions/components/TransactionFacilityActivity.vue';
import TransactionFacilityFiltersModal from '@/modules/transactions/components/TransactionFacilityFiltersModal.vue';
import TransactionFacilitySummaryHeader from '@/modules/transactions/components/TransactionFacilitySummaryHeader.vue';
import AccountReimbursementsPanel from '@/modules/accounts/components/AccountReimbursementsPanel.vue';
import { useTransactionFacility } from '@/modules/transactions/composables/useTransactionFacility';
import { useTransactionFacilityFilters } from '@/modules/transactions/composables/useTransactionFacilityFilters';
import { useAccountMemberTransfers } from '@/modules/accounts/composables/useAccountMemberTransfers';
import { useSharedAccountReimbursements } from '@/modules/accounts/composables/useSharedAccountReimbursements';
import type { AccountPendingReimbursement } from '@/modules/accounts/types';
import { getStoredAuthSession } from '@/modules/auth/lib/authSession';
import { useInfiniteScroll } from '@/modules/shared/composables/useInfiniteScroll';
import { AppText } from '@/modules/shared/components';

const route = useRoute();
const isFiltersModalOpen = ref(false);
const { activeFilters, endDate, resetPeriod, searchTerm, startDate, validationError } =
  useTransactionFacilityFilters();
const {
  transactions,
  summary,
  hasTransactions,
  hasMoreTransactions,
  hasReachedEnd,
  isLoading,
  isLoadingMore,
  loadError,
  loadTransactions,
  loadMoreTransactions,
} = useTransactionFacility();
const { accountsWithReimbursements } = useSharedAccountReimbursements();
const currentUserId = computed(() => getStoredAuthSession()?.user.id ?? null);
const {
  activeTransferKey,
  isTransferring,
  settleAllAccountReimbursements,
  settleAccountReimbursements,
  settleReimbursement,
  transferError,
} = useAccountMemberTransfers();

const transactionsPerPage = computed(() => {
  const rawValue =
    typeof route.query.perPage === 'string' ? Number(route.query.perPage) : Number.NaN;

  if (!Number.isInteger(rawValue) || rawValue <= 0) {
    return 20;
  }

  return rawValue;
});

const { target: loadMoreSentinel } = useInfiniteScroll({
  enabled: computed(() => !isLoading.value && !isLoadingMore.value && hasMoreTransactions.value),
  onIntersect: () => {
    void loadMoreTransactions();
  },
});

watch(
  [activeFilters, transactionsPerPage],
  ([nextFilters, nextPerPage]) => {
    if (!nextFilters) {
      return;
    }

    void loadTransactions(nextFilters, {
      reset: true,
      perPage: nextPerPage,
    });
  },
  { immediate: true },
);

async function reloadTransactions(): Promise<void> {
  if (!activeFilters.value) {
    return;
  }

  await loadTransactions(activeFilters.value, {
    reset: true,
    perPage: transactionsPerPage.value,
  });
}

function closeFiltersModal(): void {
  isFiltersModalOpen.value = false;
}

function openFiltersModal(): void {
  isFiltersModalOpen.value = true;
}

function handleLoadMoreRetry(): void {
  void loadMoreTransactions();
}

async function handleSettleReimbursement(
  accountId: string,
  reimbursement: AccountPendingReimbursement,
): Promise<void> {
  await settleReimbursement(accountId, reimbursement);
  await reloadTransactions();
}

async function handleSettleAccountReimbursements(
  accountId: string,
  reimbursements: AccountPendingReimbursement[],
): Promise<void> {
  await settleAccountReimbursements(accountId, reimbursements);
  await reloadTransactions();
}

async function handleSettleAllAccountReimbursements(
  accounts: Array<{ id: string; pendingReimbursements: AccountPendingReimbursement[] }>,
): Promise<void> {
  await settleAllAccountReimbursements(accounts);
  await reloadTransactions();
}

function infiniteStatusLabel(): string {
  if (isLoadingMore.value) {
    return 'Cargando más transacciones...';
  }

  if (hasReachedEnd.value) {
    return 'Has llegado al final.';
  }

  return 'Sigue desplazándote para revisar más movimientos del periodo.';
}
</script>

<template>
  <div class="space-y-5">
    <TransactionFacilitySummaryHeader :summary="summary" />

    <section v-if="transferError" class="rounded-2xl border border-(--app-color-danger) px-4 py-3">
      <AppText class="text-(--app-color-danger)!">{{ transferError }}</AppText>
    </section>

    <AccountReimbursementsPanel
      :accounts="accountsWithReimbursements"
      :active-transfer-key="activeTransferKey"
      :current-user-id="currentUserId"
      :is-transferring="isTransferring"
      title="Reembolsos pendientes"
      @settle="handleSettleReimbursement"
      @settle-all="handleSettleAllAccountReimbursements"
      @settle-account="handleSettleAccountReimbursements"
    />

    <TransactionFacilityActivity
      v-model:search-term="searchTerm"
      :has-transactions="hasTransactions"
      :infinite-status-label="infiniteStatusLabel()"
      :is-loading="isLoading"
      :load-error="loadError"
      :show-load-more-retry="Boolean(loadError && hasTransactions)"
      :transactions="transactions"
      :validation-error="validationError"
      @load-more-retry="handleLoadMoreRetry"
      @open-filters="openFiltersModal"
      @retry="reloadTransactions"
    >
      <template #loadMoreSentinel>
        <div ref="loadMoreSentinel" class="h-1" aria-hidden="true" />
      </template>
    </TransactionFacilityActivity>

    <TransactionFacilityFiltersModal
      v-model:start-date="startDate"
      v-model:end-date="endDate"
      :open="isFiltersModalOpen"
      :validation-error="validationError"
      @clear="resetPeriod"
      @close="closeFiltersModal"
    />
  </div>
</template>
