<script setup lang="ts">
import { ref, watch } from 'vue';
import type { TransactionType } from '@/modules/transactions/types';
import { AppFilterPanel, AppFilterOptions } from '@/modules/shared/components';

const props = defineProps<{
    open: boolean;
    selectedTypes: TransactionType[];
    typeOptions: readonly { value: TransactionType; label: string }[];
}>();

const emit = defineEmits<{
    apply: [values: TransactionType[]];
    close: [];
}>();

const draft = ref<TransactionType[]>([]);

watch(
    () => props.open,
    (open) => {
        if (open) draft.value = [...props.selectedTypes];
    },
    { immediate: true },
);

function apply(): void {
    emit('apply', [...draft.value]);
    emit('close');
}
</script>

<template>
    <AppFilterPanel
        id="account-transaction-filters"
        :open="props.open"
        @apply="apply"
        @clear="draft = []"
        @close="emit('close')"
    >
        <AppFilterOptions v-model="draft" label="Tipo" :options="props.typeOptions" />
    </AppFilterPanel>
</template>
