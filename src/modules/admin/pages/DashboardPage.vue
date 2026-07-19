<script setup lang="ts">
import { computed, ref } from 'vue';

import DashboardBalanceSection from '@/modules/admin/components/DashboardBalanceSection.vue';
import DashboardPeriodSummary from '@/modules/admin/components/DashboardPeriodSummary.vue';
import DashboardSubscriptionsPlanning from '@/modules/admin/components/DashboardSubscriptionsPlanning.vue';
import DashboardSummaryCards from '@/modules/admin/components/DashboardSummaryCards.vue';
import { useDashboard } from '@/modules/admin/composables/useDashboard';
import { useDashboardPeriodSummary } from '@/modules/admin/composables/useDashboardPeriodSummary';
import { AppButton, AppText } from '@/modules/shared/components';

type SavingsCadence = 'monthly' | 'biweekly';
type AccountGraphMode = 'physical' | 'virtual';

const savingsCadence = ref<SavingsCadence>('monthly');
const accountGraphMode = ref<AccountGraphMode>('physical');
const {
  dashboard,
  hasDashboardData,
  isLoading,
  loadError,
  loadDashboard,
} = useDashboard();

const {
  startDate: periodStartDate,
  endDate: periodEndDate,
  summary: periodSummary,
  isLoading: isLoadingPeriodSummary,
  loadError: periodSummaryError,
  validationError: periodSummaryValidationError,
  resetToCurrentMonth,
  loadPeriodSummary,
} = useDashboardPeriodSummary();

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

    <DashboardSubscriptionsPlanning
      v-model:savings-cadence="savingsCadence"
      :annual-spend="annualSubscriptionsSpend"
      :recommended-savings="recommendedSavings"
      :summary="dashboard.subscriptionsSummary"
    />

    <DashboardPeriodSummary
      v-model:start-date="periodStartDate"
      v-model:end-date="periodEndDate"
      :summary="periodSummary"
      :is-loading="isLoadingPeriodSummary"
      :load-error="periodSummaryError"
      :validation-error="periodSummaryValidationError"
      @reset-period="resetToCurrentMonth"
      @retry="loadPeriodSummary"
    />

  </div>
</template>
