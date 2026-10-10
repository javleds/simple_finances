<script setup lang="ts">
import { XMarkIcon } from '@heroicons/vue/24/outline';
import type { ActiveFilter } from '@/modules/shared/types/filters';
import AppButton from './AppButton.vue';

const props = defineProps<{ filters: readonly ActiveFilter[] }>();
</script>

<template>
    <div v-if="props.filters.length" class="flex flex-wrap gap-2" aria-label="Filtros aplicados">
        <AppButton
            v-for="filter in props.filters"
            :key="filter.key"
            variant="outline"
            class="min-h-9! rounded-full! px-3! py-1! text-xs!"
            :aria-label="`Quitar filtro: ${filter.label}`"
            @click="filter.remove()"
        >
            {{ filter.label }}
            <XMarkIcon class="h-3.5 w-3.5" aria-hidden="true" />
        </AppButton>
    </div>
</template>
