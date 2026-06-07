<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { use } from 'echarts/core';
import { BarChart } from 'echarts/charts';
import { GridComponent, TooltipComponent } from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';
import VChart from 'vue-echarts';
import type { CallbackDataParams } from 'echarts/types/src/util/types.js';
import { CheckIcon, XMarkIcon } from '@heroicons/vue/24/outline';

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
import { useThemeStore } from '@/stores/theme';

use([BarChart, CanvasRenderer, GridComponent, TooltipComponent]);

type SavingsCadence = 'monthly' | 'biweekly';

const savingsCadence = ref<SavingsCadence>('monthly');
const themeStore = useThemeStore();
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

const chartColors = computed(() => {
  themeStore.mode;

  if (typeof window === 'undefined') {
    return {
      surface: '#ffffff',
      border: '#dbe4f0',
      text: '#0f172a',
      textSubtle: '#64748b',
    };
  }

  const styles = window.getComputedStyle(document.documentElement);

  return {
    surface: styles.getPropertyValue('--app-color-surface').trim(),
    border: styles.getPropertyValue('--app-color-border').trim(),
    text: styles.getPropertyValue('--app-color-text').trim(),
    textSubtle: styles.getPropertyValue('--app-color-text-subtle').trim(),
  };
});

const balanceChartOption = computed(() => ({
  animationDuration: 350,
  grid: {
    left: 12,
    right: 12,
    top: 18,
    bottom: 36,
    containLabel: true,
  },
  tooltip: {
    trigger: 'axis',
    axisPointer: {
      type: 'shadow',
    },
    backgroundColor: chartColors.value.surface,
    borderColor: chartColors.value.border,
    borderWidth: 1,
    textStyle: {
      color: chartColors.value.text,
      fontFamily: 'inherit',
    },
    formatter: (params: CallbackDataParams | CallbackDataParams[]) => {
      const items = Array.isArray(params) ? params : [params];
      const firstItem = items[0];

      if (!firstItem || typeof firstItem !== 'object' || !('name' in firstItem)) {
        return '';
      }

      const value =
        typeof firstItem.value === 'number' ? firstItem.value : Number(firstItem.value ?? 0);

      return `
        <div style="min-width: 12rem;">
          <div style="font-weight: 600; margin-bottom: 0.25rem;">${String(firstItem.name)}</div>
          <div>Balance: ${formatCurrency(value)}</div>
        </div>
      `;
    },
  },
  xAxis: {
    type: 'category',
    data: dashboard.value.graphAccounts.map((account) => shortenLabel(account.accountName)),
    axisTick: {
      show: false,
    },
    axisLine: {
      lineStyle: {
        color: chartColors.value.border,
      },
    },
    axisLabel: {
      color: chartColors.value.textSubtle,
      fontSize: 11,
      interval: 0,
      rotate: 90,
    },
  },
  yAxis: {
    type: 'value',
    axisLine: {
      show: false,
    },
    splitLine: {
      lineStyle: {
        color: chartColors.value.border,
        opacity: 0.65,
      },
    },
    axisLabel: {
      color: chartColors.value.textSubtle,
      formatter: (value: number) => formatCompactCurrency(value),
    },
  },
  series: [
    {
      type: 'bar',
      barMaxWidth: 13,
      data: dashboard.value.graphAccounts.map((account) => ({
        value: account.balance,
        itemStyle: {
          color: 'transparent',
          borderColor: account.color ?? chartColors.value.textSubtle,
          borderWidth: 2,
          borderRadius: 0,
        },
      })),
    },
  ],
}));

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

function formatCompactCurrency(value: number): string {
  if (value >= 100000) {
    return `$${Math.round(value / 1000)}k`;
  }

  if (value >= 1000) {
    return `$${(value / 1000).toFixed(0)}k`;
  }

  return formatCurrency(value);
}

function shortenLabel(label: string): string {
  if (label.length <= 14) {
    return label;
  }

  return `${label.slice(0, 12)}…`;
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

    <AppCard class="rounded-3xl">
      <div class="space-y-4">
        <div class="space-y-1">
          <AppTitle as="h2" size="sm">Balance por cuenta</AppTitle>
          <AppText> Vista comparativa para leer el balance actual de cada cuenta. </AppText>
        </div>

        <VChart :option="balanceChartOption" autoresize class="h-72 max-h-[250px] w-full" />
      </div>
    </AppCard>

    <section class="grid gap-3 sm:grid-cols-3">
      <AppCard
        class="rounded-2xl bg-[linear-gradient(180deg,color-mix(in_srgb,var(--app-color-primary)_10%,transparent),transparent)] p-4!"
      >
        <div class="space-y-1">
          <AppText size="sm" tone="subtle">Cuentas activas</AppText>
          <p class="text-2xl font-semibold tracking-tight text-(--app-color-text)">
            {{ dashboard.accountsSummary.activeAccounts }}
          </p>
        </div>
      </AppCard>

      <AppCard
        class="rounded-2xl bg-[linear-gradient(180deg,color-mix(in_srgb,var(--app-color-success)_10%,transparent),transparent)] p-4!"
      >
        <div class="space-y-1">
          <AppText size="sm" tone="subtle">Cuentas compartidas</AppText>
          <p class="text-2xl font-semibold tracking-tight text-(--app-color-text)">
            {{ dashboard.accountsSummary.sharedAccounts }}
          </p>
        </div>
      </AppCard>

      <AppCard
        class="rounded-2xl bg-[linear-gradient(180deg,color-mix(in_srgb,var(--app-color-warning)_10%,transparent),transparent)] p-4!"
      >
        <div class="space-y-1">
          <AppText size="sm" tone="subtle">Por pagar</AppText>
          <p class="text-2xl font-semibold tracking-tight text-(--app-color-text)">
            {{ formatCurrency(dashboard.accountsSummary.pendingTotal) }}
          </p>
        </div>
      </AppCard>
    </section>

    <AppCard class="rounded-3xl">
      <div class="space-y-4">
        <div class="space-y-1">
          <AppTitle as="h2" size="sm">Acciones pendientes</AppTitle>
          <AppText>Completa pagos pendientes sin salir del tablero principal.</AppText>
        </div>

        <div class="space-y-2">
          <div
            v-for="group in pendingActionGroups"
            :key="group.accountId"
            class="space-y-2 rounded-2xl border bg-(--app-color-surface-muted) px-3 py-3"
            :style="{ borderColor: 'var(--app-color-border)' }"
          >
            <div
              class="flex items-start justify-between gap-3 border-b pb-2"
              :style="{ borderColor: 'var(--app-color-border)' }"
            >
              <div class="min-w-0 space-y-1">
                <div class="flex items-center gap-2">
                  <span
                    class="h-2.5 w-2.5 shrink-0 rounded-full"
                    :style="{
                      backgroundColor: group.accountColor ?? 'var(--app-color-text-subtle)',
                    }"
                  />
                  <p class="truncate text-sm font-semibold text-(--app-color-text)">
                    {{ group.accountName }}
                  </p>
                </div>
                <p class="text-xs text-(--app-color-text-subtle)">
                  {{ group.items.length }} pendientes · {{ formatCurrency(group.totalAmount) }}
                </p>
              </div>

              <button
                type="button"
                class="shrink-0 text-(--app-color-link) transition hover:text-(--app-color-link-hover) focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none"
                aria-label="Completar movimientos pendientes de la cuenta"
                @click="openCompletePendingAccount(group.accountId)"
              >
                <CheckIcon class="h-5 w-5" />
              </button>
            </div>

            <div class="space-y-1">
              <div v-for="action in group.items" :key="action.id" class="rounded-xl px-1 py-2">
                <div class="min-w-0 space-y-2">
                  <div class="flex items-start justify-between gap-3">
                    <p
                      class="[display:-webkit-box] overflow-hidden text-sm leading-5 font-semibold text-(--app-color-text) [-webkit-box-orient:vertical] [-webkit-line-clamp:2]"
                    >
                      {{ action.concept }}
                    </p>

                    <button
                      type="button"
                      class="mt-0.5 shrink-0 text-(--app-color-link) transition hover:text-(--app-color-link-hover) focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none"
                      aria-label="Completar movimiento pendiente"
                      @click="openCompletePendingAction(action.id)"
                    >
                      <CheckIcon class="h-4 w-4" />
                    </button>
                  </div>

                  <p class="text-sm font-semibold whitespace-nowrap text-(--app-color-text)">
                    {{ formatCurrency(action.amount) }}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <div
            v-if="pendingActionGroups.length === 0"
            class="rounded-xl border border-dashed px-4 py-4 text-center"
            :style="{ borderColor: 'var(--app-color-border)' }"
          >
            <AppText size="sm">No hay acciones pendientes por ahora.</AppText>
          </div>
        </div>
      </div>
    </AppCard>

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
