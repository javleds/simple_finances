<script setup lang="ts">
import { MagnifyingGlassIcon, PlusIcon } from '@heroicons/vue/24/outline';

import { AppButton, AppFilterTrigger, AppInput, AppSectionBar } from '@/modules/shared/components';

const props = defineProps<{
    canManage?: boolean;
    searchTerm: string;
    activeFilterCount: number;
    filtersOpen: boolean;
}>();

const emit = defineEmits<{
    'update:searchTerm': [value: string];
    create: [];
    openFilters: [];
}>();
</script>

<template>
    <AppSectionBar
        title="Invitaciones"
        description="Invita usuarios a colaborar dentro de esta cuenta."
    >
        <template #actions>
            <AppButton v-if="props.canManage !== false" variant="primary" @click="emit('create')">
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
                id="invitation-search"
                :model-value="props.searchTerm"
                type="search"
                placeholder="Buscar invitación por correo"
                class="pl-11"
                @update:model-value="emit('update:searchTerm', $event)"
            />
        </div>

        <AppFilterTrigger
            :count="props.activeFilterCount"
            :open="props.filtersOpen"
            controls="account-invitation-filters"
            @click="emit('openFilters')"
        />
    </div>
</template>
