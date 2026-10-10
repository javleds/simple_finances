<script setup lang="ts">
import Button from 'primevue/button';
import { ArrowPathIcon, XMarkIcon } from '@heroicons/vue/24/outline';

import type {
    AccountKindFilter,
    AccountSurfaceFilter,
    AccountStatus,
} from '@/modules/accounts/types';
import { AppModal, AppText, AppTitle } from '@/modules/shared/components';

type FilterOption<TValue extends string> = {
    value: TValue;
    label: string;
};

const props = defineProps<{
    kindOptions: readonly FilterOption<AccountKindFilter>[];
    open: boolean;
    selectedKinds: AccountKindFilter[];
    selectedStatuses: AccountStatus[];
    selectedSurfaces: AccountSurfaceFilter[];
    statusOptions: readonly AccountStatus[];
    surfaceOptions: readonly FilterOption<AccountSurfaceFilter>[];
}>();

const emit = defineEmits<{
    clear: [];
    close: [];
    toggleKind: [kind: AccountKindFilter];
    toggleStatus: [status: AccountStatus];
    toggleSurface: [surface: AccountSurfaceFilter];
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
                <AppText>Refina la lista usando el estado operativo de la cuenta.</AppText>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button
                    v-for="status in props.statusOptions"
                    :key="status"
                    severity="secondary"
                    variant="outlined"
                    type="button"
                    class="rounded-full border px-4 py-2 text-sm font-medium transition focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none"
                    :aria-pressed="props.selectedStatuses.includes(status)"
                    :class="
                        props.selectedStatuses.includes(status)
                            ? 'bg-(--app-color-primary) text-(--app-color-primary-foreground)'
                            : 'bg-(--app-color-surface-muted) text-(--app-color-text)'
                    "
                    :style="{ borderColor: 'var(--app-color-border)' }"
                    @click="emit('toggleStatus', status)"
                >
                    {{ status }}
                </Button>
            </div>

            <div class="space-y-2">
                <AppTitle as="h2" size="sm">Tipo de cuenta</AppTitle>
                <AppText>Filtra entre cuentas de crédito y débito.</AppText>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button
                    v-for="kind in props.kindOptions"
                    :key="kind.value"
                    severity="secondary"
                    variant="outlined"
                    type="button"
                    class="rounded-full border px-4 py-2 text-sm font-medium transition focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none"
                    :aria-pressed="props.selectedKinds.includes(kind.value)"
                    :class="
                        props.selectedKinds.includes(kind.value)
                            ? 'bg-(--app-color-primary) text-(--app-color-primary-foreground)'
                            : 'bg-(--app-color-surface-muted) text-(--app-color-text)'
                    "
                    :style="{ borderColor: 'var(--app-color-border)' }"
                    @click="emit('toggleKind', kind.value)"
                >
                    {{ kind.label }}
                </Button>
            </div>

            <div class="space-y-2">
                <AppTitle as="h2" size="sm">Superficie</AppTitle>
                <AppText>Filtra entre cuentas virtuales y físicas.</AppText>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button
                    v-for="surface in props.surfaceOptions"
                    :key="surface.value"
                    severity="secondary"
                    variant="outlined"
                    type="button"
                    class="rounded-full border px-4 py-2 text-sm font-medium transition focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none"
                    :aria-pressed="props.selectedSurfaces.includes(surface.value)"
                    :class="
                        props.selectedSurfaces.includes(surface.value)
                            ? 'bg-(--app-color-primary) text-(--app-color-primary-foreground)'
                            : 'bg-(--app-color-surface-muted) text-(--app-color-text)'
                    "
                    :style="{ borderColor: 'var(--app-color-border)' }"
                    @click="emit('toggleSurface', surface.value)"
                >
                    {{ surface.label }}
                </Button>
            </div>
        </div>
    </AppModal>
</template>
