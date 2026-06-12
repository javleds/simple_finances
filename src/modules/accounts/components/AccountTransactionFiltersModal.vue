<script setup lang="ts">
import { ArrowPathIcon, XMarkIcon } from '@heroicons/vue/24/outline';

import type { TransactionStatus, TransactionType } from '@/modules/transactions/types';
import { AppModal, AppText, AppTitle } from '@/modules/shared/components';

type FilterOption<TValue extends string> = {
  value: TValue;
  label: string;
};

const props = defineProps<{
  open: boolean;
  selectedStatuses: TransactionStatus[];
  selectedTypes: TransactionType[];
  statusOptions: ReadonlyArray<FilterOption<TransactionStatus>>;
  typeOptions: ReadonlyArray<FilterOption<TransactionType>>;
}>();

const emit = defineEmits<{
  clear: [];
  close: [];
  toggleStatus: [status: TransactionStatus];
  toggleType: [type: TransactionType];
}>();

function handleAction(actionKey: string): void {
  if (actionKey === 'clear') {
    emit('clear');
    return;
  }

  if (actionKey === 'close') {
    emit('close');
  }
}
</script>

<template>
  <AppModal
    :open="props.open"
    :actions="[
      { key: 'clear', label: 'Limpiar filtros', tone: 'neutral', icon: ArrowPathIcon },
      { key: 'close', label: 'Cerrar', tone: 'danger', icon: XMarkIcon, autoClose: true },
    ]"
    title="Filtros avanzados"
    variant="default"
    @action="handleAction"
    @close="emit('close')"
  >
    <div class="space-y-5">
      <div class="space-y-2">
        <AppTitle as="h2" size="sm">Estatus</AppTitle>
        <AppText>Refina la actividad según el estado de conciliación de cada movimiento.</AppText>
      </div>

      <div class="flex flex-wrap gap-2">
        <button
          v-for="status in props.statusOptions"
          :key="status.value"
          type="button"
          class="rounded-full border px-4 py-2 text-sm font-medium transition focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none"
          :class="
            props.selectedStatuses.includes(status.value)
              ? 'bg-(--app-color-primary) text-(--app-color-primary-foreground)'
              : 'bg-(--app-color-surface-muted) text-(--app-color-text)'
          "
          :style="{ borderColor: 'var(--app-color-border)' }"
          @click="emit('toggleStatus', status.value)"
        >
          {{ status.label }}
        </button>
      </div>

      <div class="space-y-2">
        <AppTitle as="h2" size="sm">Tipo</AppTitle>
        <AppText>Filtra entre ingresos y egresos.</AppText>
      </div>

      <div class="flex flex-wrap gap-2">
        <button
          v-for="type in props.typeOptions"
          :key="type.value"
          type="button"
          class="rounded-full border px-4 py-2 text-sm font-medium transition focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none"
          :class="
            props.selectedTypes.includes(type.value)
              ? 'bg-(--app-color-primary) text-(--app-color-primary-foreground)'
              : 'bg-(--app-color-surface-muted) text-(--app-color-text)'
          "
          :style="{ borderColor: 'var(--app-color-border)' }"
          @click="emit('toggleType', type.value)"
        >
          {{ type.label }}
        </button>
      </div>
    </div>
  </AppModal>
</template>
