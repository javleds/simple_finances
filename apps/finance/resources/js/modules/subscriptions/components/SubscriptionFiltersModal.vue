<script setup lang="ts">
import { ref, watch } from 'vue';
import type {
    SubscriptionFilterSelection,
    SubscriptionFrequencyType,
    SubscriptionStatusFilter,
} from '@/modules/subscriptions/types';
import { AppFilterPanel, AppFilterOptions } from '@/modules/shared/components';

type FilterOption<T extends string> = { value: T; label: string };
const props = defineProps<{
    open: boolean;
    selectedStatuses: SubscriptionStatusFilter[];
    selectedUnits: SubscriptionFrequencyType[];
    statusOptions: readonly FilterOption<SubscriptionStatusFilter>[];
    unitOptions: readonly FilterOption<SubscriptionFrequencyType>[];
}>();
const emit = defineEmits<{ apply: [selection: SubscriptionFilterSelection]; close: [] }>();
const draft = ref<SubscriptionFilterSelection>({ statuses: [], units: [] });
watch(
    () => props.open,
    (open) => {
        if (!open) return;
        draft.value = { statuses: [...props.selectedStatuses], units: [...props.selectedUnits] };
    },
    { immediate: true },
);
function clearDraft(): void {
    draft.value = { statuses: [], units: [] };
}
function selectStatuses(statuses: SubscriptionStatusFilter[]): void {
    draft.value.statuses = statuses.slice(-1);
}
function applyDraft(): void {
    emit('apply', { statuses: [...draft.value.statuses], units: [...draft.value.units] });
    emit('close');
}
</script>
<template>
    <AppFilterPanel
        id="subscription-filters"
        :open="props.open"
        @clear="clearDraft"
        @apply="applyDraft"
        @close="emit('close')"
    >
        <AppFilterOptions
            label="Estatus"
            :model-value="draft.statuses"
            :options="props.statusOptions"
            @update:model-value="selectStatuses"
        />
        <AppFilterOptions v-model="draft.units" label="Frecuencia" :options="props.unitOptions" />
    </AppFilterPanel>
</template>
