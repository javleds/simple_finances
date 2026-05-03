<script setup lang="ts">
import { computed, ref } from 'vue';
import { use } from 'echarts/core';
import { BarChart } from 'echarts/charts';
import { GridComponent, TooltipComponent } from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';
import VChart from 'vue-echarts';
import type { CallbackDataParams } from 'echarts/types/src/util/types.js';

import { accounts } from '@/modules/accounts/data/accounts';
import {
  AppButton,
  AppCard,
  AppText,
  AppToggleButton,
  AppTitle,
} from '@/modules/shared/components';
import { useThemeStore } from '@/stores/theme';

use([BarChart, CanvasRenderer, GridComponent, TooltipComponent]);

type SavingsCadence = 'monthly' | 'biweekly';

type SubscriptionSummary = {
  id: string;
  plan: string;
  annualCost: number;
};

const savingsCadence = ref<SavingsCadence>('monthly');
const themeStore = useThemeStore();

const cadenceOptions = [
  { value: 'monthly', label: 'Mensual' },
  { value: 'biweekly', label: 'Quincenal' },
] as const;

const subscriptions: SubscriptionSummary[] = [
  {
    id: 'premium-facility',
    plan: 'Plan Premium Facility',
    annualCost: 12000,
  },
  {
    id: 'additional-users',
    plan: 'Usuarios adicionales',
    annualCost: 1280 * 12,
  },
  {
    id: 'advanced-analytics',
    plan: 'Analítica avanzada',
    annualCost: 860 * 12,
  },
];

const activeAccounts = computed(() => accounts.filter((account) => account.status === 'Activo'));

const sharedAccountsCount = computed(
  () => accounts.filter((account) => account.users.length > 1).length,
);

const totalPendingPayments = computed(() =>
  accounts.reduce((sum, account) => {
    return (
      sum +
      account.users.reduce((usersSum, user) => usersSum + parseCurrency(user.pendingExpenses), 0)
    );
  }, 0),
);

const pendingActions = computed(() =>
  accounts
    .map((account) => ({
      id: account.id,
      accountName: account.name,
      amount: account.users.reduce((sum, user) => sum + parseCurrency(user.pendingExpenses), 0),
    }))
    .filter((item) => item.amount > 0)
    .sort((left, right) => right.amount - left.amount),
);

const annualSubscriptionsSpend = computed(() =>
  subscriptions.reduce((sum, subscription) => sum + subscription.annualCost, 0),
);

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
    data: accounts.map((account) => shortenLabel(account.name)),
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
    type: 'log',
    logBase: 10,
    min: 1000,
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
      barMaxWidth: 26,
      data: accounts.map((account) => ({
        value: parseCurrency(account.balance),
        itemStyle: {
          color: account.color,
          borderRadius: [10, 10, 0, 0],
        },
      })),
    },
  ],
}));

function parseCurrency(value: string): number {
  return Number(value.replace(/[^0-9.-]/g, '')) || 0;
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

function completePendingAction(): void {}
</script>

<template>
  <div class="space-y-5">
    <AppCard class="rounded-3xl">
      <div class="space-y-4">
        <div class="space-y-1">
          <AppTitle as="h2" size="sm">Balance por cuenta</AppTitle>
          <AppText>
            Vista comparativa con escala logarítmica para leer cuentas grandes y chicas sin perder
            proporción.
          </AppText>
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
            {{ activeAccounts.length }}
          </p>
        </div>
      </AppCard>

      <AppCard
        class="rounded-2xl bg-[linear-gradient(180deg,color-mix(in_srgb,var(--app-color-success)_10%,transparent),transparent)] p-4!"
      >
        <div class="space-y-1">
          <AppText size="sm" tone="subtle">Cuentas compartidas</AppText>
          <p class="text-2xl font-semibold tracking-tight text-(--app-color-text)">
            {{ sharedAccountsCount }}
          </p>
        </div>
      </AppCard>

      <AppCard
        class="rounded-2xl bg-[linear-gradient(180deg,color-mix(in_srgb,var(--app-color-warning)_10%,transparent),transparent)] p-4!"
      >
        <div class="space-y-1">
          <AppText size="sm" tone="subtle">Por pagar</AppText>
          <p class="text-2xl font-semibold tracking-tight text-(--app-color-text)">
            {{ formatCurrency(totalPendingPayments) }}
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
            v-for="action in pendingActions"
            :key="action.id"
            class="grid grid-cols-[minmax(0,1fr)_auto_auto] items-center gap-3 rounded-xl border bg-(--app-color-surface-muted) px-3 py-3"
            :style="{ borderColor: 'var(--app-color-border)' }"
          >
            <p class="truncate text-sm font-semibold text-(--app-color-text)">
              {{ action.accountName }}
            </p>
            <p class="text-sm font-semibold whitespace-nowrap text-(--app-color-text)">
              {{ formatCurrency(action.amount) }}
            </p>
            <AppButton variant="outline" class="h-9! px-3!" @click="completePendingAction">
              Completar
            </AppButton>
          </div>

          <div
            v-if="pendingActions.length === 0"
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
        <div class="space-y-1">
          <AppTitle as="h2" size="sm">Planeación de subscripciones</AppTitle>
          <AppText>
            Cambia la cadencia recomendada para separar el gasto sin acumular golpes fuertes.
          </AppText>
        </div>

        <AppToggleButton
          :model-value="savingsCadence"
          :options="cadenceOptions"
          @update:model-value="savingsCadence = $event as SavingsCadence"
        />
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
  </div>
</template>
