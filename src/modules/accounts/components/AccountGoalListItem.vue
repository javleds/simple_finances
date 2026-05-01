<script setup lang="ts">
import { AppActionMenu, AppCard } from '@/modules/shared/components';

type GoalStatus = 'on-track' | 'at-risk' | 'completed';

const props = defineProps<{
  itemId: string;
  title: string;
  detail: string;
  currentAmount: number;
  targetAmount: number;
  progress: number;
  status: GoalStatus;
  cadenceLabel: string;
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

function statusLabel(status: GoalStatus): string {
  if (status === 'completed') {
    return 'Completada';
  }

  if (status === 'at-risk') {
    return 'En riesgo';
  }

  return 'En curso';
}

function statusClasses(status: GoalStatus): string {
  if (status === 'completed') {
    return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
  }

  if (status === 'at-risk') {
    return 'bg-amber-500/12 text-amber-700 dark:text-amber-300';
  }

  return 'bg-sky-500/10 text-sky-700 dark:text-sky-300';
}

function accentStyle(status: GoalStatus): string {
  if (status === 'completed') {
    return 'linear-gradient(90deg, color-mix(in srgb, #10b981 16%, transparent), transparent 78%)';
  }

  if (status === 'at-risk') {
    return 'linear-gradient(90deg, color-mix(in srgb, #f59e0b 16%, transparent), transparent 78%)';
  }

  return 'linear-gradient(90deg, color-mix(in srgb, var(--app-color-primary) 12%, transparent), transparent 78%)';
}

function progressBarStyle(status: GoalStatus): string {
  if (status === 'completed') {
    return '#10b981';
  }

  if (status === 'at-risk') {
    return '#f59e0b';
  }

  return 'var(--app-color-primary)';
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
    class="relative overflow-hidden rounded-xl !p-3.5 shadow-none transition hover:border-(--app-color-border-strong)"
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
            {{ props.title }}
          </p>
          <p class="text-sm leading-5 text-(--app-color-text-muted)">
            {{ props.detail }}
          </p>
        </div>

        <div class="space-y-1 text-right">
          <p class="text-sm font-semibold tracking-tight text-(--app-color-text) tabular-nums">
            {{ formattedAmount(props.currentAmount) }}
          </p>
          <p
            class="text-[11px] font-medium tracking-[0.04em] text-(--app-color-text-subtle) uppercase"
          >
            de {{ formattedAmount(props.targetAmount) }}
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
            {{ props.cadenceLabel }}
          </p>
        </div>

        <p
          class="shrink-0 text-[11px] font-semibold tracking-[0.04em] text-(--app-color-text) uppercase"
        >
          {{ props.progress }}%
        </p>
      </div>

      <div class="h-2 rounded-full bg-(--app-color-surface-muted)">
        <div
          class="h-2 rounded-full"
          :style="{ width: `${props.progress}%`, backgroundColor: progressBarStyle(props.status) }"
        />
      </div>
    </div>
  </AppCard>
</template>
