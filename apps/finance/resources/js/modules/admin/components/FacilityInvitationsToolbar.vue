<script setup lang="ts">
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline';

import { AppInput, AppSectionBar } from '@/modules/shared/components';

const props = defineProps<{
    searchTerm: string;
}>();

const emit = defineEmits<{
    'update:searchTerm': [value: string];
}>();
</script>

<template>
    <AppSectionBar
        as="h1"
        title="Invitaciones"
        description="Revisa las cuentas a las que aún no te has unido y responde desde aquí."
    />

    <div class="relative flex-1">
        <div
            class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-(--app-color-text-subtle)"
        >
            <MagnifyingGlassIcon class="h-5 w-5" />
        </div>
        <AppInput
            id="facility-invitation-search"
            :model-value="props.searchTerm"
            type="search"
            placeholder="Buscar invitación por cuenta o invitador"
            class="pl-11"
            @update:model-value="emit('update:searchTerm', $event)"
        />
    </div>
</template>
