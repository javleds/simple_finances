<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import {
  currentMonthRange,
  validateTransactionPeriod,
  type TransactionPeriod,
} from '@/modules/transactions/lib/transactionPeriod';
import { AppDatePicker, AppFilterPanel } from '@/modules/shared/components';

const props = defineProps<{ open: boolean; startDate: string | null; endDate: string | null }>();
const emit = defineEmits<{ apply: [period: TransactionPeriod]; close: [] }>();
const draftStart = ref<string | null>(null);
const draftEnd = ref<string | null>(null);
const validationError = computed(() => validateTransactionPeriod(draftStart.value, draftEnd.value));

watch(
  () => props.open,
  (open) => {
    if (!open) return;
    draftStart.value = props.startDate;
    draftEnd.value = props.endDate;
  },
  { immediate: true },
);

function resetDraft(): void {
  const range = currentMonthRange();
  draftStart.value = range.startDate;
  draftEnd.value = range.endDate;
}

function applyDraft(): void {
  if (validationError.value || !draftStart.value || !draftEnd.value) return;
  emit('apply', { startDate: draftStart.value, endDate: draftEnd.value });
  emit('close');
}
</script>

<template>
  <AppFilterPanel
    id="transaction-period-filters"
    :open="props.open"
    :apply-disabled="Boolean(validationError)"
    clear-label="Mes actual"
    @apply="applyDraft"
    @clear="resetDraft"
    @close="emit('close')"
  >
    <fieldset class="m-0 min-w-0 border-0 p-0">
      <legend class="mb-3 text-sm font-semibold text-(--app-color-text)">Periodo</legend>
      <div class="grid gap-4 sm:grid-cols-2">
        <AppDatePicker
          id="transaction-facility-start-date"
          v-model="draftStart"
          label="Desde"
          :clearable="false"
        />
        <AppDatePicker
          id="transaction-facility-end-date"
          v-model="draftEnd"
          label="Hasta"
          :clearable="false"
          :error="validationError ?? undefined"
        />
      </div>
    </fieldset>
  </AppFilterPanel>
</template>
