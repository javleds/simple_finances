<script setup lang="ts">
import { MagnifyingGlassIcon, PlusIcon } from '@heroicons/vue/24/outline';

import { AppButton, AppInput, AppSectionBar } from '@/modules/shared/components';

const props = defineProps<{
  searchTerm: string;
}>();

const emit = defineEmits<{
  'update:searchTerm': [value: string];
  create: [];
  openFilters: [];
}>();
</script>

<template>
  <AppSectionBar
    title="Ingresos fijos"
    description="Cada regla define un ingreso fijo y agrupa sus distribuciones asociadas."
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
        id="distribution-search"
        :model-value="props.searchTerm"
        type="search"
        placeholder="Buscar regla por nombre"
        class="pl-11"
        @update:model-value="emit('update:searchTerm', $event)"
      />
    </div>

    <AppButton variant="secondary" @click="emit('openFilters')">Filtros</AppButton>
  </div>
</template>
