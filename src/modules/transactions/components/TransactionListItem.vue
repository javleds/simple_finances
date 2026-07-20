<script setup lang="ts">
import { computed } from 'vue';
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';

import { AppActionMenu, AppCard } from '@/modules/shared/components';

type TransactionItemType = 'income' | 'expense';

const props = defineProps<{
  itemId: string;
  concept: string;
  amount: number;
  type: TransactionItemType;
  dateLabel: string;
  creatorName: string | null;
  accountName?: string | null;
  metaLabel?: string | null;
  showActions?: boolean;
  pendingReimbursementAmount?: number;
  receivableReimbursementAmount?: number;
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

const actionMenuItems = computed(() => {
  return [
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
  ];
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

function creatorLabel(): string {
  return props.creatorName?.trim() || 'Usuario no disponible';
}

function secondaryLabel(): string {
  const metaLabel = props.metaLabel?.trim();

  if (metaLabel) {
    return metaLabel;
  }

  const accountName = props.accountName?.trim();

  if (accountName) {
    return `${accountName} · ${creatorLabel()}`;
  }

  return creatorLabel();
}

function hasPendingReimbursement(): boolean {
  return Boolean(props.pendingReimbursementAmount && props.pendingReimbursementAmount > 0);
}

function hasReceivableReimbursement(): boolean {
  return Boolean(props.receivableReimbursementAmount && props.receivableReimbursementAmount > 0);
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

      <div class="flex flex-col items-end gap-1.5">
        <p
          class="shrink-0 text-sm font-semibold tracking-tight whitespace-nowrap tabular-nums sm:text-base"
          :class="amountClasses(props.type)"
        >
          <span class="mr-1">{{ signLabel(props.type) }}</span
          >{{ formattedAmount(props.amount) }}
        </p>

        <div
          v-if="hasPendingReimbursement() || hasReceivableReimbursement()"
          class="flex max-w-34 flex-col items-end gap-1"
        >
          <p
            v-if="hasPendingReimbursement()"
            class="inline-flex rounded-full bg-[color-mix(in_srgb,var(--app-color-warning)_14%,transparent)] px-2 py-0.5 text-[11px] leading-4 font-semibold whitespace-nowrap text-(--app-color-warning)"
          >
            Debes {{ formattedAmount(props.pendingReimbursementAmount ?? 0) }}
          </p>

          <p
            v-if="hasReceivableReimbursement()"
            class="inline-flex rounded-full bg-[color-mix(in_srgb,#10b981_14%,transparent)] px-2 py-0.5 text-[11px] leading-4 font-semibold whitespace-nowrap text-emerald-700 dark:text-emerald-300"
          >
            Te deben {{ formattedAmount(props.receivableReimbursementAmount ?? 0) }}
          </p>
        </div>
      </div>

      <AppActionMenu
        v-if="props.showActions"
        class="shrink-0"
        :actions="actionMenuItems"
        @delete="handleDelete"
        @edit="handleEdit"
      />
      <div v-else class="h-6 w-7 shrink-0" aria-hidden="true" />

      <div class="col-span-3 flex min-w-0 items-center justify-between gap-3">
        <p class="min-w-0 truncate text-[11px] font-medium text-(--app-color-text-subtle)">
          {{ secondaryLabel() }}
        </p>

        <p
          class="shrink-0 text-[11px] font-medium tracking-[0.04em] whitespace-nowrap text-(--app-color-text-subtle) uppercase"
        >
          {{ props.dateLabel }}
        </p>
      </div>
    </div>
  </AppCard>
</template>
