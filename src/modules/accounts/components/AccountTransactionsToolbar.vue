<script setup lang="ts">
import {
  AdjustmentsHorizontalIcon,
  MagnifyingGlassIcon,
  PlusIcon,
} from '@heroicons/vue/24/outline';

import {
  AppButton,
  AppIconButton,
  AppInput,
  AppSectionBar,
} from '@/modules/shared/components';

const searchTerm = defineModel<string>('searchTerm', { required: true });

const emit = defineEmits<{
  create: [];
  openFilters: [];
}>();
</script>

<template>
  <div class="space-y-4">
    <AppSectionBar title="Transacciones">
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
          id="transaction-search"
          v-model="searchTerm"
          type="search"
          placeholder="Buscar transacción por concepto"
          class="pl-11"
        />
      </div>

      <AppIconButton ariaLabel="Abrir filtros avanzados" @click="emit('openFilters')">
        <AdjustmentsHorizontalIcon class="h-5 w-5" />
      </AppIconButton>
    </div>
  </div>
</template>
