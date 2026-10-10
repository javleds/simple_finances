<script setup lang="ts">
import {
  AdjustmentsHorizontalIcon,
  MagnifyingGlassIcon,
  PlusIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline';

import { AppButton, AppInput, AppSectionBar } from '@/modules/shared/components';

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
    title="Gestión de cuentas"
    description="La navegación por default es entrar al detalle de cada cuenta."
  >
    <template #actions>
      <AppButton variant="primary" @click="emit('create')">
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

    <AppButton
      variant="outline"
      aria-controls="account-filters"
      :aria-expanded="props.isFiltersOpen"
      @click="emit('openFilters')"
    >
      <AdjustmentsHorizontalIcon class="h-5 w-5" />
      <span
        >Filtros<span v-if="props.filterChips.length"> · {{ props.filterChips.length }}</span></span
      >
    </AppButton>
  </div>
  <div v-if="props.filterChips.length" class="flex flex-wrap gap-2" aria-label="Filtros aplicados">
    <AppButton
      v-for="chip in props.filterChips"
      :key="chip.key"
      variant="outline"
      class="min-h-9! rounded-full! px-3! py-1! text-xs!"
      :aria-label="`Quitar filtro: ${chip.label}`"
      @click="chip.remove()"
    >
      {{ chip.label }}
      <XMarkIcon class="h-3.5 w-3.5" aria-hidden="true" />
    </AppButton>
  </div>
</template>
