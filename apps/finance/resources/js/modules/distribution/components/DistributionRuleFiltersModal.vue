<script setup lang="ts">
import { ref, watch } from 'vue';
import type {
    DistributionFrequency,
    DistributionRuleFilterSelection,
} from '@/modules/distribution/types';
import { AppFilterPanel, AppFilterOptions } from '@/modules/shared/components';
const props = defineProps<{
    frequencyOptions: readonly { value: DistributionFrequency; label: string }[];
    open: boolean;
    selectedFrequencies: DistributionFrequency[];
}>();
const emit = defineEmits<{ apply: [selection: DistributionRuleFilterSelection]; close: [] }>();
const draft = ref<DistributionFrequency[]>([]);
watch(
    () => props.open,
    (open) => {
        if (!open) return;
        draft.value = [...props.selectedFrequencies];
    },
    { immediate: true },
);
function applyDraft(): void {
    emit('apply', { frequencies: [...draft.value] });
    emit('close');
}
</script>
<template>
    <AppFilterPanel
        id="distribution-filters"
        :open="props.open"
        @clear="draft = []"
        @apply="applyDraft"
        @close="emit('close')"
    >
        <AppFilterOptions v-model="draft" label="Frecuencia" :options="props.frequencyOptions" />
    </AppFilterPanel>
</template>
