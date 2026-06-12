<script setup lang="ts">
import AccountTransactionFiltersModal from '@/modules/accounts/components/AccountTransactionFiltersModal.vue';
import type { TransactionStatus, TransactionType } from '@/modules/transactions/types';

const props = defineProps<{
  open: boolean;
  selectedStatuses: TransactionStatus[];
  selectedTypes: TransactionType[];
}>();

const emit = defineEmits<{
  clear: [];
  close: [];
  toggleStatus: [status: TransactionStatus];
  toggleType: [type: TransactionType];
}>();

const transactionStatusOptions = [
  { value: 'completed', label: 'Completado' },
  { value: 'pending', label: 'Pendiente' },
] as const;

const transactionTypeOptions = [
  { value: 'income', label: 'Ingreso' },
  { value: 'expense', label: 'Egreso' },
] as const;
</script>

<template>
  <AccountTransactionFiltersModal
    :open="props.open"
    :selected-statuses="props.selectedStatuses"
    :selected-types="props.selectedTypes"
    :status-options="transactionStatusOptions"
    :type-options="transactionTypeOptions"
    @clear="emit('clear')"
    @close="emit('close')"
    @toggle-status="emit('toggleStatus', $event)"
    @toggle-type="emit('toggleType', $event)"
  />
</template>
