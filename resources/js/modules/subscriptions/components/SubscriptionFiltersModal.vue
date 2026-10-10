<script setup lang="ts">
import Button from 'primevue/button';
import { ArrowPathIcon, XMarkIcon } from '@heroicons/vue/24/outline';

import type {
    SubscriptionFrequencyType,
    SubscriptionStatusFilter,
} from '@/modules/subscriptions/types';
import { AppModal, AppText, AppTitle } from '@/modules/shared/components';

type FilterOption<TValue extends string> = {
    value: TValue;
    label: string;
};

const props = defineProps<{
    open: boolean;
    selectedStatuses: SubscriptionStatusFilter[];
    selectedUnits: SubscriptionFrequencyType[];
    statusOptions: readonly FilterOption<SubscriptionStatusFilter>[];
    unitOptions: readonly FilterOption<SubscriptionFrequencyType>[];
}>();

const emit = defineEmits<{
    clear: [];
    close: [];
    toggleStatus: [status: SubscriptionStatusFilter];
    toggleUnit: [unit: SubscriptionFrequencyType];
}>();
</script>

<template>
    <AppModal
        :open="props.open"
        :actions="[
            { key: 'clear', label: 'Limpiar filtros', tone: 'neutral', icon: ArrowPathIcon },
            { key: 'close', label: 'Cerrar', tone: 'danger', icon: XMarkIcon, autoClose: true },
        ]"
        title="Filtros avanzados"
        variant="default"
        @action="$event === 'clear' ? emit('clear') : emit('close')"
        @close="emit('close')"
    >
        <div class="space-y-5">
            <div class="space-y-2">
                <AppTitle as="h2" size="sm">Estatus</AppTitle>
                <AppText
                    >Filtra la cobertura según el estado operativo de cada suscripción.</AppText
                >
            </div>

            <div class="flex flex-wrap gap-2">
                <Button
                    v-for="status in props.statusOptions"
                    :key="status.value"
                    severity="secondary"
                    variant="outlined"
                    type="button"
                    class="rounded-full border px-4 py-2 text-sm font-medium transition focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none"
                    :aria-pressed="props.selectedStatuses.includes(status.value)"
                    :class="
                        props.selectedStatuses.includes(status.value)
                            ? 'bg-(--app-color-primary) text-(--app-color-primary-foreground)'
                            : 'bg-(--app-color-surface-muted) text-(--app-color-text)'
                    "
                    :style="{ borderColor: 'var(--app-color-border)' }"
                    @click="emit('toggleStatus', status.value)"
                >
                    {{ status.label }}
                </Button>
            </div>

            <div class="space-y-2">
                <AppTitle as="h2" size="sm">Frecuencia</AppTitle>
                <AppText>Refina la lista por la unidad principal de recurrencia.</AppText>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button
                    v-for="unit in props.unitOptions"
                    :key="unit.value"
                    severity="secondary"
                    variant="outlined"
                    type="button"
                    class="rounded-full border px-4 py-2 text-sm font-medium transition focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none"
                    :aria-pressed="props.selectedUnits.includes(unit.value)"
                    :class="
                        props.selectedUnits.includes(unit.value)
                            ? 'bg-(--app-color-primary) text-(--app-color-primary-foreground)'
                            : 'bg-(--app-color-surface-muted) text-(--app-color-text)'
                    "
                    :style="{ borderColor: 'var(--app-color-border)' }"
                    @click="emit('toggleUnit', unit.value)"
                >
                    {{ unit.label }}
                </Button>
            </div>
        </div>
    </AppModal>
</template>
