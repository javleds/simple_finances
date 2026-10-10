<script setup lang="ts">
import Button from 'primevue/button';
import { ArrowPathIcon, XMarkIcon } from '@heroicons/vue/24/outline';

import { formatDistributionFrequency } from '@/modules/distribution/schemas/distributionSchemas';
import type { DistributionFrequency } from '@/modules/distribution/types';
import { AppModal, AppText, AppTitle } from '@/modules/shared/components';

type FrequencyOption = {
    value: DistributionFrequency;
    label: string;
};

const props = defineProps<{
    frequencyOptions: readonly FrequencyOption[];
    open: boolean;
    selectedFrequencies: DistributionFrequency[];
}>();

const emit = defineEmits<{
    clear: [];
    close: [];
    toggleFrequency: [frequency: DistributionFrequency];
}>();
</script>

<template>
    <AppModal
        :open="props.open"
        :actions="[
            { key: 'clear', label: 'Limpiar filtros', tone: 'neutral', icon: ArrowPathIcon },
            { key: 'close', label: 'Cerrar', tone: 'danger', icon: XMarkIcon, autoClose: true },
        ]"
        title="Filtros"
        variant="default"
        @action="$event === 'clear' ? emit('clear') : emit('close')"
        @close="emit('close')"
    >
        <div class="space-y-5">
            <div class="space-y-2">
                <AppTitle as="h2" size="sm">Frecuencia</AppTitle>
                <AppText>Filtra las reglas según su recurrencia.</AppText>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button
                    v-for="option in props.frequencyOptions"
                    :key="option.value"
                    severity="secondary"
                    variant="outlined"
                    type="button"
                    class="rounded-full border px-4 py-2 text-sm font-medium transition focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none"
                    :aria-pressed="props.selectedFrequencies.includes(option.value)"
                    :class="
                        props.selectedFrequencies.includes(option.value)
                            ? 'bg-(--app-color-primary) text-(--app-color-primary-foreground)'
                            : 'bg-(--app-color-surface-muted) text-(--app-color-text)'
                    "
                    :style="{ borderColor: 'var(--app-color-border)' }"
                    @click="emit('toggleFrequency', option.value)"
                >
                    {{ formatDistributionFrequency(option.value) }}
                </Button>
            </div>
        </div>
    </AppModal>
</template>
