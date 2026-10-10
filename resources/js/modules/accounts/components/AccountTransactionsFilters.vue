<script setup lang="ts">
import AccountTransactionFiltersModal from '@/modules/accounts/components/AccountTransactionFiltersModal.vue';
import type { TransactionType } from '@/modules/transactions/types';

const props = defineProps<{
    open: boolean;
    selectedTypes: TransactionType[];
}>();

const emit = defineEmits<{
    apply: [types: TransactionType[]];
    close: [];
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
        @apply="emit('apply', $event)"
        @close="emit('close')"
    />
</template>
