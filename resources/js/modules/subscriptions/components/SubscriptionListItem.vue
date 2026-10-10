<script setup lang="ts">
import { AppActionMenu, AppCard } from '@/modules/shared/components';

type SubscriptionStatus = 'active' | 'cancelled';

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
  if (status === 'cancelled') {
    return 'Cancelada';
  }

  return 'Activa';
}

function statusClasses(status: SubscriptionStatus): string {
  if (status === 'cancelled') {
    return 'bg-(--app-color-surface-muted) text-(--app-color-text-muted)';
  }

  return 'bg-[color-mix(in_srgb,var(--app-color-success)_10%,transparent)] text-(--app-color-success)';
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
    class="relative overflow-hidden rounded-(--app-radius-control) p-4! shadow-none transition hover:border-(--app-color-border-strong) sm:rounded-(--app-radius-control) sm:p-3.5!"
  >
    <div class="relative space-y-3">
      <div
        class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-3 sm:grid-cols-[minmax(0,1fr)_auto_auto]"
      >
        <div class="min-w-0 space-y-1">
          <p class="text-sm leading-5 font-semibold break-words text-(--app-color-text)">
            {{ props.plan }}
          </p>
          <p class="text-sm leading-5 break-words text-(--app-color-text-muted)">
            {{ props.cycle }}
          </p>
        </div>

        <div
          class="col-start-1 row-start-2 space-y-1 sm:col-start-auto sm:row-start-auto sm:text-right"
        >
          <p
            class="text-sm font-semibold tracking-tight text-(--app-color-text) tabular-nums"
          >
            {{ formattedAmount(props.amount) }}
          </p>
          <p
            class="text-[11px] font-medium tracking-[0.04em] text-(--app-color-text-subtle) uppercase"
          >
            Próximo cargo
          </p>
        </div>

        <AppActionMenu
          class="col-start-2 row-start-1 shrink-0 sm:col-start-auto sm:row-start-auto"
          @delete="handleDelete"
          @edit="handleEdit"
        />
      </div>

      <div class="flex items-center justify-between gap-3">
        <div class="flex min-w-0 items-center gap-2">
          <span
            class="inline-flex shrink-0 items-center rounded-(--app-radius-control) px-2 py-0.5 text-[10px] font-semibold tracking-[0.04em] uppercase"
            :class="statusClasses(props.status)"
          >
            {{ statusLabel(props.status) }}
          </span>
          <p
            class="text-[11px] font-medium tracking-[0.04em] break-words text-(--app-color-text-subtle) uppercase"
          >
            {{ props.nextCharge }}
          </p>
        </div>
      </div>
    </div>
  </AppCard>
</template>
