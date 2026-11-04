<script setup lang="ts">
import { ref, watch } from 'vue';
import type { AccountGoalStatusFilter } from '@/modules/accounts/composables/useAccountGoalFilters';
import { AppFilterPanel, AppFilterOptions } from '@/modules/shared/components';

const props = defineProps<{
    open: boolean;
    selectedStatuses: AccountGoalStatusFilter[];
    options: readonly { value: AccountGoalStatusFilter; label: string }[];
}>();

const emit = defineEmits<{
    apply: [values: AccountGoalStatusFilter[]];
    close: [];
}>();

const draft = ref<AccountGoalStatusFilter[]>([]);

watch(
    () => props.open,
    (open) => {
        if (open) draft.value = [...props.selectedStatuses];
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
        id="account-goal-filters"
        :open="props.open"
        @apply="apply"
        @clear="draft = []"
        @close="emit('close')"
    >
        <AppFilterOptions v-model="draft" label="Estatus" :options="props.options" />
    </AppFilterPanel>
</template>
