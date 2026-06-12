<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';

import DashboardBalanceSection from '@/modules/admin/components/DashboardBalanceSection.vue';
import DashboardCompletePendingModal from '@/modules/admin/components/DashboardCompletePendingModal.vue';
import DashboardPendingActions from '@/modules/admin/components/DashboardPendingActions.vue';
import DashboardSubscriptionsPlanning from '@/modules/admin/components/DashboardSubscriptionsPlanning.vue';
import DashboardSummaryCards from '@/modules/admin/components/DashboardSummaryCards.vue';
import { useDashboard } from '@/modules/admin/composables/useDashboard';
import { useDashboardPendingActions } from '@/modules/admin/composables/useDashboardPendingActions';
import {
  AppButton,
  AppText,
} from '@/modules/shared/components';

type SavingsCadence = 'monthly' | 'biweekly';
type AccountGraphMode = 'physical' | 'virtual';

const savingsCadence = ref<SavingsCadence>('monthly');
const accountGraphMode = ref<AccountGraphMode>('physical');
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

const {
  closeCompletePendingAction,
  confirmCompletePendingAction,
  isCompletePendingActionOpen,
  openCompletePendingAccount,
  openCompletePendingAction,
  pendingActionGroups,
  selectedPendingAccountActions,
  selectedPendingAction,
} = useDashboardPendingActions({
  clearCompleteError,
  completePendingTransactions,
  dashboard,
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
