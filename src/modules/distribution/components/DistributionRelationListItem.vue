<script setup lang="ts">
import { AppActionMenu, AppCard } from '@/modules/shared/components';

type DistributionRelationType = 'transfer' | 'saving';

const props = defineProps<{
  concept: string;
  amount: number;
  type: DistributionRelationType;
}>();

const emit = defineEmits<{
  edit: [];
  delete: [];
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

function typeLabel(type: DistributionRelationType): string {
  if (type === 'saving') {
    return 'Ahorro';
  }

  return 'Transferencia';
}

function typeClasses(type: DistributionRelationType): string {
  if (type === 'saving') {
    return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
  }

  return 'bg-sky-500/10 text-sky-700 dark:text-sky-300';
}

function handleEdit(): void {
  emit('edit');
}

function handleDelete(): void {
  emit('delete');
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
          props.type === 'saving'
            ? 'linear-gradient(90deg, color-mix(in srgb, #10b981 14%, transparent), transparent 78%)'
            : 'linear-gradient(90deg, color-mix(in srgb, var(--app-color-primary) 10%, transparent), transparent 78%)',
      }"
    />

    <div class="relative space-y-3">
      <div class="grid grid-cols-[minmax(0,1fr)_auto_auto] items-start gap-3">
        <div class="min-w-0">
          <p
            class="overflow-hidden text-sm font-semibold leading-5 text-[var(--app-color-text)] [display:-webkit-box] [-webkit-box-orient:vertical] [-webkit-line-clamp:2]"
          >
            {{ props.concept }}
          </p>
        </div>

        <div class="space-y-1 text-right">
          <p class="text-sm font-semibold tabular-nums tracking-tight text-[var(--app-color-text)] sm:text-base">
            {{ formattedAmount(props.amount) }}
          </p>
        </div>

        <AppActionMenu class="shrink-0" @delete="handleDelete" @edit="handleEdit" />
      </div>

      <div class="flex items-center gap-2">
        <span
          class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.04em]"
          :class="typeClasses(props.type)"
        >
          {{ typeLabel(props.type) }}
        </span>
      </div>
    </div>
  </AppCard>
</template>
