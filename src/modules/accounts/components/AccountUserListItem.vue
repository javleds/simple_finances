<script setup lang="ts">
import { AppActionMenu, AppCard } from '@/modules/shared/components';

type UserAccessStatus = 'active' | 'invited';

const props = defineProps<{
  itemId: string;
  name: string;
  email: string;
  roleLabel: string;
  accessLabel: string;
  allocationPercentage: number;
  custodyAmount: number;
  settlementAmount: number;
  status: UserAccessStatus;
}>();

const emit = defineEmits<{
  edit: [itemId: string];
  delete: [itemId: string];
}>();

function statusLabel(status: UserAccessStatus): string {
  if (status === 'invited') {
    return 'Invitado';
  }

  return 'Activo';
}

function statusClasses(status: UserAccessStatus): string {
  if (status === 'invited') {
    return 'bg-amber-500/12 text-amber-700 dark:text-amber-300';
  }

  return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
}

function accentStyle(status: UserAccessStatus): string {
  if (status === 'invited') {
    return 'linear-gradient(90deg, color-mix(in srgb, #f59e0b 16%, transparent), transparent 78%)';
  }

  return 'linear-gradient(90deg, color-mix(in srgb, var(--app-color-primary) 12%, transparent), transparent 78%)';
}

function handleEdit(): void {
  emit('edit', props.itemId);
}

function handleDelete(): void {
  emit('delete', props.itemId);
}

function formatCurrency(value: number): string {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value);
}

function settlementLabel(value: number): string {
  if (value > 0) {
    return `Por recibir ${formatCurrency(value)}`;
  }

  if (value < 0) {
    return `Por pagar ${formatCurrency(Math.abs(value))}`;
  }

  return 'Sin deuda';
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
            {{ props.name }}
          </p>
          <p class="truncate text-sm leading-5 text-(--app-color-text-muted)">
            {{ props.email }}
          </p>
        </div>

        <div class="space-y-1 text-right">
          <p class="text-sm font-semibold tracking-tight text-(--app-color-text) tabular-nums">
            {{ props.allocationPercentage }}%
          </p>
          <p
            class="text-[11px] font-medium tracking-[0.04em] text-(--app-color-text-subtle) uppercase"
          >
            Participación
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
            {{ props.roleLabel }} · {{ props.accessLabel }}
          </p>
        </div>

        <div class="shrink-0 text-right">
          <p class="text-[11px] font-semibold tracking-[0.04em] text-(--app-color-text) uppercase">
            {{ settlementLabel(props.settlementAmount) }}
          </p>
          <p class="text-[11px] font-medium text-(--app-color-text-subtle)">
            Custodia {{ formatCurrency(props.custodyAmount) }}
          </p>
        </div>
      </div>
    </div>
  </AppCard>
</template>
