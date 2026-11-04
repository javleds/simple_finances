<script setup lang="ts">
import { AppActionMenu, AppCard } from '@/modules/shared/components';

type DistributionRelationType = 'transfer' | 'savings';

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
  if (type === 'savings') {
    return 'Ahorro';
  }

  return 'Transferencia';
}

function typeClasses(type: DistributionRelationType): string {
  if (type === 'savings') {
    return 'bg-[color-mix(in_srgb,var(--app-color-success)_10%,transparent)] text-(--app-color-success)';
  }

  return 'bg-[color-mix(in_srgb,var(--app-color-primary)_10%,transparent)] text-(--app-color-primary)';
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
    class="relative overflow-hidden rounded-(--app-radius-control) p-4! shadow-none transition hover:border-(--app-color-border-strong) sm:rounded-(--app-radius-control) sm:p-3.5!"
  >
    <div class="relative space-y-3">
      <div
        class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-3 sm:grid-cols-[minmax(0,1fr)_auto_auto]"
      >
        <div class="min-w-0">
          <p class="text-sm leading-5 font-semibold break-words text-(--app-color-text)">
            {{ props.concept }}
          </p>
        </div>

        <div
          class="col-start-1 row-start-2 space-y-1 sm:col-start-auto sm:row-start-auto sm:text-right"
        >
          <p
            class="text-sm font-semibold tracking-tight text-(--app-color-text) tabular-nums sm:text-base"
          >
            {{ formattedAmount(props.amount) }}
          </p>
        </div>

        <AppActionMenu
          class="col-start-2 row-start-1 shrink-0 sm:col-start-auto sm:row-start-auto"
          @delete="handleDelete"
          @edit="handleEdit"
        />
      </div>

      <div class="flex items-center gap-2">
        <span
          class="inline-flex shrink-0 items-center rounded-(--app-radius-control) px-2 py-0.5 text-[10px] font-semibold tracking-[0.04em] uppercase"
          :class="typeClasses(props.type)"
        >
          {{ typeLabel(props.type) }}
        </span>
      </div>
    </div>
  </AppCard>
</template>
