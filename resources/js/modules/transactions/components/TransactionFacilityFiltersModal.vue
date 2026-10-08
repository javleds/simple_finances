<script setup lang="ts">
import { ArrowPathIcon, XMarkIcon } from '@heroicons/vue/24/outline';

import { AppDatePicker, AppModal, AppText, AppTitle } from '@/modules/shared/components';

const startDate = defineModel<string | null>('startDate', { required: true });
const endDate = defineModel<string | null>('endDate', { required: true });

const props = defineProps<{
  open: boolean;
  validationError: string | null;
}>();

const emit = defineEmits<{
  clear: [];
  close: [];
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
      { key: 'clear', label: 'Mes actual', tone: 'neutral', icon: ArrowPathIcon },
      { key: 'close', label: 'Cerrar', tone: 'danger', icon: XMarkIcon, autoClose: true },
    ]"
    title="Filtros"
    variant="default"
    @action="handleAction"
    @close="emit('close')"
  >
    <div class="space-y-5">
      <div class="space-y-2">
        <AppTitle as="h2" size="sm">Periodo</AppTitle>
        <AppText>Selecciona el rango de movimientos completados que quieres revisar.</AppText>
      </div>

      <div class="grid gap-3">
        <AppDatePicker
          id="transaction-facility-start-date"
          v-model="startDate"
          label="Desde"
          :clearable="false"
          :error="props.validationError ?? undefined"
        />

        <AppDatePicker
          id="transaction-facility-end-date"
          v-model="endDate"
          label="Hasta"
          :clearable="false"
          :error="props.validationError ?? undefined"
        />
      </div>
    </div>
  </AppModal>
</template>
