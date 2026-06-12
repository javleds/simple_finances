<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { CheckIcon, XMarkIcon } from '@heroicons/vue/24/outline';

import DashboardBalanceSection from '@/modules/admin/components/DashboardBalanceSection.vue';
import DashboardPendingActions from '@/modules/admin/components/DashboardPendingActions.vue';
import DashboardSummaryCards from '@/modules/admin/components/DashboardSummaryCards.vue';
import { useDashboard } from '@/modules/admin/composables/useDashboard';
import type {
  DashboardPendingAction,
  DashboardPendingActionGroup,
} from '@/modules/admin/types/dashboard';
import {
  AppButton,
  AppCard,
  AppModal,
  AppText,
  AppToggleButton,
  AppTitle,
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

const cadenceOptions = [
  { value: 'monthly', label: 'Mensual' },
  { value: 'biweekly', label: 'Quincenal' },
] as const;

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

function formatCurrency(value: number): string {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value);
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

    <AppCard class="rounded-3xl">
      <div class="flex items-center justify-between gap-4">
        <div class="flex flex-col items-center gap-4 space-y-1">
          <AppTitle as="h2" size="sm">Planeación de subscripciones</AppTitle>

          <AppToggleButton
            :model-value="savingsCadence"
            :options="cadenceOptions"
            @update:model-value="savingsCadence = $event as SavingsCadence"
          />
        </div>
      </div>
    </AppCard>

    <section class="grid gap-3 sm:grid-cols-2">
      <AppCard
        class="rounded-2xl bg-[linear-gradient(180deg,color-mix(in_srgb,var(--app-color-primary)_8%,transparent),transparent)] p-4!"
      >
        <div class="space-y-1">
          <AppText size="sm" tone="subtle">Gasto anual en subscripciones</AppText>
          <p class="text-2xl font-semibold tracking-tight text-(--app-color-text)">
            {{ formatCurrency(annualSubscriptionsSpend) }}
          </p>
        </div>
      </AppCard>

      <AppCard
        class="rounded-2xl bg-[linear-gradient(180deg,color-mix(in_srgb,var(--app-color-secondary)_10%,transparent),transparent)] p-4!"
      >
        <div class="space-y-1">
          <AppText size="sm" tone="subtle">
            Ahorro {{ savingsCadence === 'monthly' ? 'mensual' : 'quincenal' }} recomendado
          </AppText>
          <p class="text-2xl font-semibold tracking-tight text-(--app-color-text)">
            {{ formatCurrency(recommendedSavings) }}
          </p>
        </div>
      </AppCard>
    </section>

    <AppModal
      :open="isCompletePendingActionOpen"
      :actions="[
        { key: 'close', label: 'Cancelar', tone: 'danger', icon: XMarkIcon, autoClose: true },
        {
          key: 'confirm-complete',
          label: isCompletingPendingActions ? 'Completando...' : 'Completar movimiento',
          tone: 'primary',
          icon: CheckIcon,
          disabled: isCompletingPendingActions,
        },
      ]"
      title="Completar movimiento"
      variant="warning"
      @action="$event === 'confirm-complete' && void confirmCompletePendingAction()"
      @close="closeCompletePendingAction"
    >
      <div class="space-y-3">
        <AppText v-if="completeError" class="text-(--app-color-danger)!">
          {{ completeError }}
        </AppText>

        <AppText v-if="selectedPendingAction">
          Vas a marcar como completado el pendiente de
          <strong>{{ selectedPendingAction.accountName }}</strong>
          por
          <strong>{{ formatCurrency(selectedPendingAction.amount) }}</strong
          >.
        </AppText>

        <AppText v-else-if="selectedPendingAccountActions.length > 0">
          Vas a marcar como completados los
          <strong>{{ selectedPendingAccountActions.length }} pendientes de esta cuenta</strong>
          por un total de
          <strong>
            {{
              formatCurrency(
                selectedPendingAccountActions.reduce((sum, item) => sum + item.amount, 0),
              )
            }} </strong
          >.
        </AppText>

        <AppText v-else>
          Confirma si quieres marcar este movimiento pendiente como completado.
        </AppText>
      </div>
    </AppModal>
  </div>
</template>
