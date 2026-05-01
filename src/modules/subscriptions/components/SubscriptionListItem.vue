<script setup lang="ts">
import { AppActionMenu, AppCard } from '@/modules/shared/components';

type SubscriptionStatus = 'active' | 'pending-renewal' | 'paused';

const props = defineProps<{
  itemId: string;
  plan: string;
  cycle: string;
  nextCharge: string;
  amount: number;
  status: SubscriptionStatus;
}>();

const emit = defineEmits<{
  edit: [itemId: string];
  delete: [itemId: string];
}>();

const currencyFormatter = new Intl.NumberFormat('es-MX', {
  style: 'currency',
  currency: 'MXN',
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
});

function statusLabel(status: SubscriptionStatus): string {
  if (status === 'paused') {
    return 'Pausada';
  }

  if (status === 'pending-renewal') {
    return 'Por renovar';
  }

  return 'Activa';
}

function statusClasses(status: SubscriptionStatus): string {
  if (status === 'paused') {
    return 'bg-slate-500/10 text-slate-600 dark:text-slate-300';
  }

  if (status === 'pending-renewal') {
    return 'bg-amber-500/12 text-amber-700 dark:text-amber-300';
  }

  return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
}

function accentStyle(status: SubscriptionStatus): string {
  if (status === 'paused') {
    return 'linear-gradient(90deg, color-mix(in srgb, #64748b 14%, transparent), transparent 78%)';
  }

  if (status === 'pending-renewal') {
    return 'linear-gradient(90deg, color-mix(in srgb, #f59e0b 16%, transparent), transparent 78%)';
  }

  return 'linear-gradient(90deg, color-mix(in srgb, #0f766e 18%, transparent), transparent 78%)';
}

function formattedAmount(amount: number): string {
  return currencyFormatter.format(amount);
}

function handleEdit(): void {
  emit('edit', props.itemId);
}

function handleDelete(): void {
  emit('delete', props.itemId);
}
</script>

<template>
  <AppCard
    class="relative overflow-hidden rounded-xl p-3.5! shadow-none transition hover:border-(--app-color-border-strong)"
  >
    <div
      class="pointer-events-none absolute inset-y-0 left-0 w-24 opacity-90"
      :style="{ background: accentStyle(props.status) }"
    />

    <div class="relative space-y-3">
      <div class="grid grid-cols-[minmax(0,1fr)_auto_auto] items-start gap-3">
        <div class="min-w-0 space-y-1">
          <p
            class="[display:-webkit-box] overflow-hidden text-sm leading-5 font-semibold text-(--app-color-text) [-webkit-box-orient:vertical] [-webkit-line-clamp:2]"
          >
            {{ props.plan }}
          </p>
          <p class="truncate text-sm leading-5 text-(--app-color-text-muted)">
            {{ props.cycle }}
          </p>
        </div>

        <div class="space-y-1 text-right">
          <p class="text-sm font-semibold tracking-tight text-(--app-color-text) tabular-nums">
            {{ formattedAmount(props.amount) }}
          </p>
          <p
            class="text-[11px] font-medium tracking-[0.04em] text-(--app-color-text-subtle) uppercase"
          >
            Próximo cargo
          </p>
        </div>

        <AppActionMenu class="shrink-0" @delete="handleDelete" @edit="handleEdit" />
      </div>

      <div class="flex items-center justify-between gap-3">
        <div class="flex min-w-0 items-center gap-2">
          <span
            class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[10px] font-semibold tracking-[0.04em] uppercase"
            :class="statusClasses(props.status)"
          >
            {{ statusLabel(props.status) }}
          </span>
          <p
            class="truncate text-[11px] font-medium tracking-[0.04em] text-(--app-color-text-subtle) uppercase"
          >
            {{ props.nextCharge }}
          </p>
        </div>
      </div>
    </div>
  </AppCard>
</template>
