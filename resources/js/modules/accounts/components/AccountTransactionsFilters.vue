<script setup lang="ts">
import AccountTransactionFiltersModal from '@/modules/accounts/components/AccountTransactionFiltersModal.vue';
import type { TransactionType } from '@/modules/transactions/types';

const props = withDefaults(
    defineProps<{
        open: boolean;
        accountId?: string;
        selectedCategories?: string[];
        hasAttachments?: boolean | null;
        selectedTypes: TransactionType[];
    }>(),
    { hasAttachments: null, selectedCategories: () => [] },
);

const emit = defineEmits<{
    apply: [types: TransactionType[]];
    applyAdditional: [values: { categories: string[]; hasAttachments: boolean | null }];
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
        :account-id="props.accountId"
        :selected-categories="props.selectedCategories"
        :has-attachments="props.hasAttachments"
        :selected-types="props.selectedTypes"
        :type-options="transactionTypeOptions"
        @apply="emit('apply', $event)"
        @apply-additional="emit('applyAdditional', $event)"
        @close="emit('close')"
    />
</template>
