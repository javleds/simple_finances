<script setup lang="ts">
import { computed } from 'vue';
import { CheckCircleIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';

import { AppActionMenu, AppCard } from '@/modules/shared/components';

type TransactionItemType = 'income' | 'expense';
type TransactionItemStatus = 'completed' | 'pending';

const props = defineProps<{
  itemId: string;
  concept: string;
  amount: number;
  type: TransactionItemType;
  status: TransactionItemStatus;
  dateLabel: string;
  creatorName: string | null;
  canComplete?: boolean;
  showActions?: boolean;
}>();

const emit = defineEmits<{
  complete: [itemId: string];
  edit: [itemId: string];
  delete: [itemId: string];
}>();

const currencyFormatter = new Intl.NumberFormat('es-MX', {
  style: 'currency',
  currency: 'MXN',
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
});

const actionMenuItems = computed(() => {
  const actions = [];

  if (props.canComplete) {
    actions.push({
      key: 'complete',
      label: 'Completar',
      icon: CheckCircleIcon,
      tone: 'success' as const,
    });
  }

  actions.push(
    {
      key: 'edit',
      label: 'Editar',
      icon: PencilSquareIcon,
      tone: 'default' as const,
    },
    {
      key: 'delete',
      label: 'Eliminar',
      icon: TrashIcon,
      tone: 'danger' as const,
    },
  );

  return actions;
});

function formattedAmount(amount: number): string {
  return currencyFormatter.format(amount);
}

function amountClasses(type: TransactionItemType): string {
  if (type === 'income') {
    return 'text-emerald-700 dark:text-emerald-300';
  }

  return 'text-red-700 dark:text-red-300';
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

function creatorLabel(): string {
  return props.creatorName?.trim() || 'Usuario no disponible';
}

function handleEdit(): void {
  emit('edit', props.itemId);
}

function handleDelete(): void {
  emit('delete', props.itemId);
}

function handleAction(actionKey: string): void {
  if (actionKey !== 'complete') {
    return;
  }

  emit('complete', props.itemId);
}
</script>

<template>
  <AppCard
    class="relative overflow-hidden rounded-xl p-3.5! shadow-none transition hover:border-(--app-color-border-strong)"
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

    <div class="relative grid grid-cols-[minmax(0,1fr)_auto_auto] items-start gap-x-3 gap-y-2">
      <div class="min-w-0">
        <p
          class="[display:-webkit-box] overflow-hidden text-sm leading-5 font-semibold text-(--app-color-text) [-webkit-box-orient:vertical] [-webkit-line-clamp:2]"
        >
          {{ props.concept }}
        </p>
      </div>

      <div class="flex flex-col items-end gap-1">
        <p
          class="shrink-0 text-sm font-semibold tracking-tight whitespace-nowrap tabular-nums sm:text-base"
          :class="amountClasses(props.type)"
        >
          <span class="mr-1">{{ signLabel(props.type) }}</span
          >{{ formattedAmount(props.amount) }}
        </p>
      </div>

      <AppActionMenu
        v-if="props.showActions"
        class="shrink-0"
        :actions="actionMenuItems"
        @action="handleAction"
        @delete="handleDelete"
        @edit="handleEdit"
      />
      <div v-else class="h-6 w-7 shrink-0" aria-hidden="true" />

      <div class="col-span-3 flex min-w-0 items-center justify-between gap-3">
        <p class="min-w-0 truncate text-[11px] font-medium text-(--app-color-text-subtle)">
          {{ creatorLabel() }}
        </p>

        <div class="flex shrink-0 items-center gap-2">
          <p
            class="text-[11px] font-medium tracking-[0.04em] whitespace-nowrap text-(--app-color-text-subtle) uppercase"
          >
            {{ props.dateLabel }}
          </p>

          <span
            class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[10px] font-semibold tracking-[0.04em] uppercase"
            :class="statusClasses(props.status)"
          >
            {{ statusLabel(props.status) }}
          </span>
        </div>
      </div>
    </div>
  </AppCard>
</template>
