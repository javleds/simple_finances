<script setup lang="ts">
import AccountTransactionFiltersModal from '@/modules/accounts/components/AccountTransactionFiltersModal.vue';
import type { TransactionType } from '@/modules/transactions/types';

const props = defineProps<{
  open: boolean;
  selectedTypes: TransactionType[];
}>();

const emit = defineEmits<{
  clear: [];
  close: [];
  toggleType: [type: TransactionType];
}>();

const transactionTypeOptions = [
  { value: 'income', label: 'Ingreso' },
  { value: 'expense', label: 'Egreso' },
] as const;
</script>

<template>
  <AccountTransactionFiltersModal
    :open="props.open"
    :selected-types="props.selectedTypes"
    :type-options="transactionTypeOptions"
    @clear="emit('clear')"
    @close="emit('close')"
    @toggle-type="emit('toggleType', $event)"
  />
</template>
