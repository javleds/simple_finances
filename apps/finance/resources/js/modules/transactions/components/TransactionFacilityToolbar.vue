<script setup lang="ts">
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline';

import {
  AppActiveFilters,
  AppFilterTrigger,
  AppInput,
  AppSectionBar,
  AppText,
} from '@/modules/shared/components';

import type { ActiveFilter } from '@/modules/shared/types/filters';

const props = defineProps<{ filterChips: readonly ActiveFilter[]; isFiltersOpen: boolean }>();

const searchTerm = defineModel<string>('searchTerm', { required: true });

const emit = defineEmits<{
  openFilters: [];
}>();
</script>

<template>
  <div class="space-y-4">
    <AppSectionBar title="Actividad" />

    <div class="flex items-center gap-3">
      <div class="relative flex-1">
        <div
          class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-(--app-color-text-subtle)"
        >
          <MagnifyingGlassIcon class="h-5 w-5" />
        </div>
        <AppInput
          id="transaction-facility-search"
          v-model="searchTerm"
          type="search"
          placeholder="Buscar por concepto"
          class="pl-11"
        />
      </div>

      <AppFilterTrigger
        :count="props.filterChips.length"
        :open="props.isFiltersOpen"
        controls="transaction-period-filters"
        @click="emit('openFilters')"
      />
    </div>
    <AppActiveFilters :filters="props.filterChips" />
    <AppText v-if="!props.filterChips.length" size="sm" tone="subtle">Periodo: mes actual</AppText>
  </div>
</template>
