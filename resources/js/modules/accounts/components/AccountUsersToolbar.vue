<script setup lang="ts">
import { MagnifyingGlassIcon, PlusIcon } from '@heroicons/vue/24/outline';

import { AppButton, AppInput, AppSectionBar } from '@/modules/shared/components';

const props = defineProps<{ canManage?: boolean }>();

const searchTerm = defineModel<string>('searchTerm', { required: true });

const emit = defineEmits<{
    create: [];
}>();
</script>

<template>
    <div class="space-y-4">
        <AppSectionBar
            title="Usuarios"
            description="Miembros reales compartidos en la cuenta y su porcentaje asignado."
        >
            <template #actions>
                <AppButton
                    v-if="props.canManage !== false"
                    variant="primary"
                    aria-label="Agregar usuario"
                    @click="emit('create')"
                >
                    <PlusIcon class="h-4 w-4" />
                </AppButton>
            </template>
        </AppSectionBar>

        <div class="relative flex-1">
            <div
                class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-(--app-color-text-subtle)"
            >
                <MagnifyingGlassIcon class="h-5 w-5" />
            </div>
            <AppInput
                id="user-search"
                v-model="searchTerm"
                type="search"
                placeholder="Buscar usuario por nombre o correo"
                class="pl-11"
            />
        </div>
    </div>
</template>
