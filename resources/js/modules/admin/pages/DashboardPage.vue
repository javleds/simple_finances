<script setup lang="ts">
import Message from 'primevue/message';
import { computed, ref } from 'vue';

import DashboardBalanceSection from '@/modules/admin/components/DashboardBalanceSection.vue';
import DashboardPeriodSummary from '@/modules/admin/components/DashboardPeriodSummary.vue';
import DashboardSubscriptionsPlanning from '@/modules/admin/components/DashboardSubscriptionsPlanning.vue';
import DashboardSummaryCards from '@/modules/admin/components/DashboardSummaryCards.vue';
import AccountReimbursementsPanel from '@/modules/accounts/components/AccountReimbursementsPanel.vue';
import { useDashboard } from '@/modules/admin/composables/useDashboard';
import { useDashboardPeriodSummary } from '@/modules/admin/composables/useDashboardPeriodSummary';
import { useAccountMemberTransfers } from '@/modules/accounts/composables/useAccountMemberTransfers';
import { useSharedAccountReimbursements } from '@/modules/accounts/composables/useSharedAccountReimbursements';
import { getStoredAuthSession } from '@/modules/auth/lib/authSession';
import { AppButton, AppText, AppTitle } from '@/modules/shared/components';

type SavingsCadence = 'monthly' | 'biweekly';
type AccountGraphMode = 'physical' | 'virtual';

const savingsCadence = ref<SavingsCadence>('monthly');
const accountGraphMode = ref<AccountGraphMode>('physical');
const { dashboard, hasDashboardData, isLoading, loadError, loadDashboard } = useDashboard();

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
    <div class="space-y-6 lg:grid lg:grid-cols-2 lg:items-start lg:gap-6 lg:space-y-0">
        <header class="space-y-1 lg:col-span-full">
            <AppTitle as="h1" size="md">Resumen financiero</AppTitle>
            <AppText>Balances, reembolsos y planeación de tus cuentas.</AppText>
        </header>
        <section
            v-if="loadError && !hasDashboardData"
            class="space-y-3 rounded-(--app-radius-control) border px-4 py-6 text-center lg:col-span-full"
            :style="{ borderColor: 'var(--app-color-border)' }"
        >
            <AppText>{{ loadError }}</AppText>
            <div class="flex justify-center">
                <AppButton variant="secondary" @click="loadDashboard">Reintentar</AppButton>
            </div>
        </section>

        <section
            v-else-if="isLoading && !hasDashboardData"
            class="rounded-(--app-radius-control) border px-4 py-10 text-center lg:col-span-full"
            :style="{ borderColor: 'var(--app-color-border)' }"
        >
            <AppText>Cargando dashboard...</AppText>
        </section>

        <Message v-if="loadError && hasDashboardData" severity="error">
            {{ loadError }}
        </Message>

        <DashboardBalanceSection
            v-model:graph-mode="accountGraphMode"
            :accounts="filteredGraphAccounts"
        />

        <DashboardSummaryCards
            class="lg:col-span-full lg:row-start-2"
            :summary="dashboard.accountsSummary"
        />

        <Message v-if="transferError" severity="error">{{ transferError }}</Message>

        <AccountReimbursementsPanel
            :accounts="accountsWithReimbursements"
            :active-transfer-key="activeTransferKey"
            :current-user-id="currentUserId"
            :is-transferring="isTransferring"
            title="Reembolsos pendientes"
            @settle="settleReimbursement"
            @settle-all="settleAllAccountReimbursements"
            @settle-account="settleAccountReimbursements"
        />

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
