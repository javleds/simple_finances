<script setup lang="ts">
import { MagnifyingGlassIcon, PlusIcon } from '@heroicons/vue/24/outline';

import {
    AppActiveFilters,
    AppButton,
    AppFilterTrigger,
    AppInput,
    AppSectionBar,
} from '@/modules/shared/components';

const props = defineProps<{
    searchTerm: string;
    filterChips: ReadonlyArray<{ key: string; label: string; remove: () => void }>;
    isFiltersOpen: boolean;
}>();

const emit = defineEmits<{
    'update:searchTerm': [value: string];
    create: [];
    openFilters: [];
}>();
</script>

<template>
    <AppSectionBar
        as="h1"
        title="Gestión de cuentas"
        description="Selecciona una cuenta para consultar su detalle y movimientos."
    >
        <template #actions>
            <AppButton variant="primary" aria-label="Crear cuenta" @click="emit('create')">
                <PlusIcon class="h-4 w-4" />
            </AppButton>
        </template>
    </AppSectionBar>

    <div class="flex items-center gap-3">
        <div class="relative flex-1">
            <div
                class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-(--app-color-text-subtle)"
            >
                <MagnifyingGlassIcon class="h-5 w-5" />
            </div>
            <AppInput
                id="account-search"
                :model-value="props.searchTerm"
                type="search"
                placeholder="Buscar cuenta por nombre"
                class="pl-11"
                @update:model-value="emit('update:searchTerm', $event)"
            />
        </div>

        <AppFilterTrigger
            :count="props.filterChips.length"
            :open="props.isFiltersOpen"
            controls="account-filters"
            @click="emit('openFilters')"
        />
    </div>
    <AppActiveFilters :filters="props.filterChips" />
</template>
