<script setup lang="ts">
import { AppCard } from '@/modules/shared/components';

type TransactionItemType = 'income' | 'expense';
type TransactionItemStatus = 'completed' | 'pending';

const props = defineProps<{
  concept: string;
  amount: number;
  type: TransactionItemType;
  status: TransactionItemStatus;
  dateLabel: string;
}>();

const currencyFormatter = new Intl.NumberFormat('es-MX', {
  style: 'currency',
  currency: 'MXN',
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
});

function formattedAmount(amount: number): string {
  return currencyFormatter.format(amount);
}

function typeLabel(type: TransactionItemType): string {
  if (type === 'income') {
    return 'Ingreso';
  }

  return 'Egreso';
}

function amountClasses(type: TransactionItemType): string {
  if (type === 'income') {
    return 'text-emerald-700 dark:text-emerald-300';
  }

  return 'text-[var(--app-color-text)]';
}

function signLabel(type: TransactionItemType): string {
  if (type === 'income') {
    return '+';
  }

  return '−';
}

function statusClasses(status: TransactionItemStatus): string {
  if (status === 'completed') {
    return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
  }

  return 'bg-amber-500/12 text-amber-700 dark:text-amber-300';
}

function statusLabel(status: TransactionItemStatus): string {
  if (status === 'completed') {
    return 'Completado';
  }

  return 'Pendiente';
}
</script>

<template>
  <AppCard
    class="relative overflow-hidden rounded-xl !p-3.5 shadow-none transition hover:border-[var(--app-color-border-strong)]"
  >
    <div
      class="pointer-events-none absolute inset-y-0 left-0 w-24 opacity-90"
      :style="{
        background:
          type === 'income'
            ? 'linear-gradient(90deg, color-mix(in srgb, #10b981 14%, transparent), transparent 78%)'
            : 'linear-gradient(90deg, color-mix(in srgb, var(--app-color-primary) 10%, transparent), transparent 78%)',
      }"
    />

    <div class="relative grid grid-cols-[minmax(0,1fr)_auto] items-start gap-x-3 gap-y-2">
      <div class="min-w-0">
        <p
          class="overflow-hidden text-sm font-semibold leading-5 text-[var(--app-color-text)] [display:-webkit-box] [-webkit-box-orient:vertical] [-webkit-line-clamp:2]"
        >
          {{ props.concept }}
        </p>
      </div>

      <p
        class="shrink-0 whitespace-nowrap text-sm font-semibold tabular-nums tracking-tight sm:text-base"
        :class="amountClasses(props.type)"
      >
        <span class="mr-1">{{ signLabel(props.type) }}</span>{{ formattedAmount(props.amount) }}
      </p>

      <div class="flex min-w-0 items-center gap-2">
        <span
          class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.04em]"
          :class="statusClasses(props.status)"
        >
          {{ statusLabel(props.status) }}
        </span>

        <p class="truncate text-[11px] font-medium uppercase tracking-[0.04em] text-[var(--app-color-text-subtle)]">
          {{ typeLabel(props.type) }} · {{ props.dateLabel }}
        </p>
      </div>
    </div>
  </AppCard>
</template>
