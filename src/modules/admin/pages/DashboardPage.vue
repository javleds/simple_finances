<script setup lang="ts">
import { computed, ref } from 'vue';
import { use } from 'echarts/core';
import { BarChart } from 'echarts/charts';
import { GridComponent, TooltipComponent } from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';
import VChart from 'vue-echarts';
import type { CallbackDataParams } from 'echarts/types/src/util/types.js';
import { CheckIcon, XMarkIcon } from '@heroicons/vue/24/outline';

import { accounts } from '@/modules/accounts/data/accounts';
import {
  AppButton,
  AppCard,
  AppIconButton,
  AppModal,
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

type PendingTransactionItem = {
  id: string;
  accountId: string;
  accountName: string;
  concept: string;
  amount: number;
  date: string;
};

type PendingTransactionGroup = {
  accountId: string;
  accountName: string;
  accountColor: string;
  totalAmount: number;
  items: PendingTransactionItem[];
};

const savingsCadence = ref<SavingsCadence>('monthly');
const themeStore = useThemeStore();
const isCompletePendingActionOpen = ref(false);
const selectedPendingActionId = ref<string | null>(null);
const selectedPendingActionIds = ref<string[]>([]);

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

const pendingTransactionDates = [
  '2026-05-02T10:30:00.000Z',
  '2026-05-01T18:15:00.000Z',
  '2026-04-30T12:00:00.000Z',
  '2026-04-29T09:45:00.000Z',
  '2026-04-28T16:20:00.000Z',
  '2026-04-27T14:10:00.000Z',
] as const;

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

const pendingActions = computed<PendingTransactionItem[]>(() =>
  accounts
    .flatMap((account) =>
      account.users
        .filter((user) => parseCurrency(user.pendingExpenses) > 0)
        .map((user, index) => {
          return {
            id: `${account.id}-${user.id}`,
            accountId: account.id,
            accountName: account.name,
            concept: `Egreso pendiente por comprobar de ${user.name}`,
            amount: parseCurrency(user.pendingExpenses),
            date: pendingTransactionDates[index % pendingTransactionDates.length] ?? pendingTransactionDates[0],
          };
        }),
    )
    .sort((left, right) => parseDate(right.date) - parseDate(left.date)),
);

const pendingActionGroups = computed<PendingTransactionGroup[]>(() =>
  accounts
    .map((account) => {
      const items = pendingActions.value
        .filter((item) => item.accountId === account.id)
        .sort((left, right) => parseDate(right.date) - parseDate(left.date));

      if (items.length === 0) {
        return null;
      }

      return {
        accountId: account.id,
        accountName: account.name,
        accountColor: account.color,
        totalAmount: items.reduce((sum, item) => sum + item.amount, 0),
        items,
      };
    })
    .filter((group): group is PendingTransactionGroup => group !== null)
    .sort((left, right) => parseDate(right.items[0]?.date) - parseDate(left.items[0]?.date)),
);

const pendingActionsCountLabel = computed(() => {
  const count = selectedPendingActionIds.value.length;

  return `${count} movimiento${count === 1 ? '' : 's'} seleccionado${count === 1 ? '' : 's'}`;
});

const selectedPendingAction = computed(() => {
  if (!selectedPendingActionId.value) {
    return null;
  }

  return pendingActions.value.find((item) => item.id === selectedPendingActionId.value) ?? null;
});

const selectedPendingActions = computed(() =>
  pendingActions.value.filter((item) => selectedPendingActionIds.value.includes(item.id)),
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
      barMaxWidth: 13,
      data: accounts.map((account) => ({
        value: parseCurrency(account.balance),
        itemStyle: {
          color: 'transparent',
          borderColor: account.color,
          borderWidth: 2,
          borderRadius: 0,
        },
      })),
    },
  ],
}));

function parseCurrency(value: string): number {
  return Number(value.replace(/[^0-9.-]/g, '')) || 0;
}

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
  selectedPendingActionId.value = actionId;
  selectedPendingActionIds.value = [];
  isCompletePendingActionOpen.value = true;
}

function openBatchCompletePendingActions(): void {
  if (selectedPendingActionIds.value.length === 0) {
    return;
  }

  selectedPendingActionId.value = null;
  isCompletePendingActionOpen.value = true;
}

function closeCompletePendingAction(): void {
  isCompletePendingActionOpen.value = false;
  selectedPendingActionId.value = null;
}

function confirmCompletePendingAction(): void {
  selectedPendingActionIds.value = [];
  closeCompletePendingAction();
}

function togglePendingActionSelection(actionId: string): void {
  if (selectedPendingActionIds.value.includes(actionId)) {
    selectedPendingActionIds.value = selectedPendingActionIds.value.filter((id) => id !== actionId);
    return;
  }

  selectedPendingActionIds.value = [...selectedPendingActionIds.value, actionId];
}
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

        <div
          v-if="selectedPendingActionIds.length > 0"
          class="flex items-center justify-between gap-3 rounded-xl border bg-[color-mix(in_srgb,var(--app-color-primary)_8%,transparent)] px-3 py-3"
          :style="{ borderColor: 'var(--app-color-border)' }"
        >
          <AppText size="sm">{{ pendingActionsCountLabel }}</AppText>

          <AppButton variant="outline" class="h-9! px-3!" @click="openBatchCompletePendingActions">
            Completar selección
          </AppButton>
        </div>

        <div class="space-y-2">
          <div
            v-for="group in pendingActionGroups"
            :key="group.accountId"
            class="space-y-2 rounded-2xl border bg-(--app-color-surface-muted) px-3 py-3"
            :style="{ borderColor: 'var(--app-color-border)' }"
          >
            <div class="flex items-start justify-between gap-3 border-b pb-2" :style="{ borderColor: 'var(--app-color-border)' }">
              <div class="min-w-0 space-y-1">
                <div class="flex items-center gap-2">
                  <span
                    class="h-2.5 w-2.5 shrink-0 rounded-full"
                    :style="{ backgroundColor: group.accountColor }"
                  />
                  <p class="truncate text-sm font-semibold text-(--app-color-text)">
                    {{ group.accountName }}
                  </p>
                </div>
                <p class="text-xs text-(--app-color-text-subtle)">
                  {{ group.items.length }} pendientes · {{ formatCurrency(group.totalAmount) }}
                </p>
              </div>
            </div>

            <div class="space-y-1">
              <div
                v-for="action in group.items"
                :key="action.id"
                class="flex items-start gap-3 rounded-xl px-1 py-2"
              >
                <input
                  :checked="selectedPendingActionIds.includes(action.id)"
                  type="checkbox"
                  class="mt-0.5 h-4 w-4 rounded border-(--app-color-input-border) text-(--app-color-primary) focus:ring-(--app-color-focus-ring)"
                  @change="togglePendingActionSelection(action.id)"
                />

                <div class="min-w-0 flex-1 space-y-2">
                  <p
                    class="[display:-webkit-box] overflow-hidden text-sm leading-5 font-semibold text-(--app-color-text) [-webkit-box-orient:vertical] [-webkit-line-clamp:2]"
                  >
                    {{ action.concept }}
                  </p>

                  <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-semibold whitespace-nowrap text-(--app-color-text)">
                      {{ formatCurrency(action.amount) }}
                    </p>

                    <AppIconButton
                      ariaLabel="Completar movimiento pendiente"
                      @click="openCompletePendingAction(action.id)"
                    >
                      <CheckIcon class="h-4 w-4" />
                    </AppIconButton>
                  </div>
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

    <AppModal
      :open="isCompletePendingActionOpen"
      :actions="[
        { key: 'close', label: 'Cancelar', tone: 'danger', icon: XMarkIcon, autoClose: true },
        { key: 'confirm-complete', label: 'Completar movimiento', tone: 'primary', icon: CheckIcon },
      ]"
      title="Completar movimiento"
      variant="warning"
      @action="($event === 'confirm-complete') && confirmCompletePendingAction()"
      @close="closeCompletePendingAction"
    >
      <div class="space-y-3">
        <AppText v-if="selectedPendingAction">
          Vas a marcar como completado el pendiente de
          <strong>{{ selectedPendingAction.accountName }}</strong>
          por
          <strong>{{ formatCurrency(selectedPendingAction.amount) }}</strong>.
        </AppText>

        <AppText v-else-if="selectedPendingActions.length > 0">
          Vas a marcar como completados
          <strong>{{ selectedPendingActions.length }} movimientos pendientes</strong>
          por un total de
          <strong>
            {{
              formatCurrency(
                selectedPendingActions.reduce((sum, item) => sum + item.amount, 0),
              )
            }}
          </strong>.
        </AppText>

        <AppText v-else>
          Confirma si quieres marcar este movimiento pendiente como completado.
        </AppText>
      </div>
    </AppModal>
  </div>
</template>
