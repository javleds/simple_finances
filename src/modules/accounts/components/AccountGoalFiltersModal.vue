<script setup lang="ts">
import { ArrowPathIcon, XMarkIcon } from '@heroicons/vue/24/outline';

import { AppModal, AppText, AppTitle } from '@/modules/shared/components';

type GoalStatusOption = {
  value: string;
  label: string;
};

const props = defineProps<{
  open: boolean;
  options: ReadonlyArray<GoalStatusOption>;
  selectedStatuses: string[];
}>();

const emit = defineEmits<{
  clear: [];
  close: [];
  toggleStatus: [status: string];
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
        <AppText>Filtra metas según su nivel de avance.</AppText>
      </div>

      <div class="flex flex-wrap gap-2">
        <button
          v-for="status in props.options"
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
    </div>
  </AppModal>
</template>
