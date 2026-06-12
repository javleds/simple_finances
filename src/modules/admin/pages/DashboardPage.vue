<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';

import DashboardBalanceSection from '@/modules/admin/components/DashboardBalanceSection.vue';
import DashboardCompletePendingModal from '@/modules/admin/components/DashboardCompletePendingModal.vue';
import DashboardPendingActions from '@/modules/admin/components/DashboardPendingActions.vue';
import DashboardSubscriptionsPlanning from '@/modules/admin/components/DashboardSubscriptionsPlanning.vue';
import DashboardSummaryCards from '@/modules/admin/components/DashboardSummaryCards.vue';
import { useDashboard } from '@/modules/admin/composables/useDashboard';
import type {
  DashboardPendingAction,
  DashboardPendingActionGroup,
} from '@/modules/admin/types/dashboard';
import {
  AppButton,
  AppText,
} from '@/modules/shared/components';

type SavingsCadence = 'monthly' | 'biweekly';
type AccountGraphMode = 'physical' | 'virtual';

const savingsCadence = ref<SavingsCadence>('monthly');
const accountGraphMode = ref<AccountGraphMode>('physical');
const isCompletePendingActionOpen = ref(false);
const selectedPendingActionId = ref<string | null>(null);
const selectedPendingAccountId = ref<string | null>(null);
const {
  dashboard,
  hasDashboardData,
  isLoading,
  isCompletingPendingActions,
  loadError,
  completeError,
  clearCompleteError,
  loadDashboard,
  completePendingTransactions,
} = useDashboard();

const pendingActions = computed<DashboardPendingAction[]>(() =>
  [...dashboard.value.pendingActions].sort(
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

const annualSubscriptionsSpend = computed(() => dashboard.value.subscriptionsSummary.annualTotal);

const recommendedSavings = computed(() => {
  const divisor = savingsCadence.value === 'monthly' ? 12 : 24;
  return annualSubscriptionsSpend.value / divisor;
});

const filteredGraphAccounts = computed(() =>
  dashboard.value.graphAccounts.filter(
    (account) => account.isVirtual === (accountGraphMode.value === 'virtual'),
  ),
);

onMounted(() => {
  void loadDashboard();
});

function parseDate(value?: string): number {
  if (!value) {
    return 0;
  }

  return Date.parse(value);
}

function openCompletePendingAction(actionId: string): void {
  clearCompleteError();
  selectedPendingActionId.value = actionId;
  selectedPendingAccountId.value = null;
  isCompletePendingActionOpen.value = true;
}

function openCompletePendingAccount(accountId: string): void {
  clearCompleteError();
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

  const wasCompleted = await completePendingTransactions(transactionIds);

  if (wasCompleted) {
    closeCompletePendingAction();
  }
}
</script>

<template>
  <div class="space-y-5">
    <section
      v-if="loadError && !hasDashboardData"
      class="space-y-3 rounded-2xl border px-4 py-6 text-center"
      :style="{ borderColor: 'var(--app-color-border)' }"
    >
      <AppText>{{ loadError }}</AppText>
      <div class="flex justify-center">
        <AppButton variant="secondary" @click="loadDashboard">Reintentar</AppButton>
      </div>
    </section>

    <section
      v-else-if="isLoading && !hasDashboardData"
      class="rounded-2xl border px-4 py-10 text-center"
      :style="{ borderColor: 'var(--app-color-border)' }"
    >
      <AppText>Cargando dashboard...</AppText>
    </section>

    <section
      v-if="loadError && hasDashboardData"
      class="rounded-2xl border border-(--app-color-danger) px-4 py-3"
    >
      <AppText class="text-(--app-color-danger)!">
        {{ loadError }}
      </AppText>
    </section>

    <DashboardBalanceSection
      v-model:graph-mode="accountGraphMode"
      :accounts="filteredGraphAccounts"
    />

    <DashboardSummaryCards :summary="dashboard.accountsSummary" />

    <DashboardPendingActions
      :groups="pendingActionGroups"
      @complete-account="openCompletePendingAccount"
      @complete-action="openCompletePendingAction"
    />

    <DashboardSubscriptionsPlanning
      v-model:savings-cadence="savingsCadence"
      :annual-spend="annualSubscriptionsSpend"
      :recommended-savings="recommendedSavings"
    />

    <DashboardCompletePendingModal
      :open="isCompletePendingActionOpen"
      :complete-error="completeError"
      :is-completing="isCompletingPendingActions"
      :selected-account-actions="selectedPendingAccountActions"
      :selected-action="selectedPendingAction"
      @close="closeCompletePendingAction"
      @confirm="confirmCompletePendingAction"
    />
  </div>
</template>
