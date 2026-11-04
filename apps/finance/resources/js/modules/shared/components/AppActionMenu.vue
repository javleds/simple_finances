<script setup lang="ts">
import type { Component } from 'vue';
import { EllipsisHorizontalIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { computed, ref, useId } from 'vue';
import Button from 'primevue/button';
import Menu from 'primevue/menu';

type ActionMenuItem = {
    key: string;
    label: string;
    icon: Component;
    tone?: 'default' | 'danger' | 'success';
};

const props = withDefaults(
    defineProps<{
        actions?: ReadonlyArray<ActionMenuItem>;
    }>(),
    {
        actions: () => [],
    },
);

const emit = defineEmits<{
    action: [key: string];
    edit: [];
    delete: [];
}>();

const menuRef = ref<InstanceType<typeof Menu> | null>(null);
const isOpen = ref(false);
const menuId = useId();
const defaultActions: ReadonlyArray<ActionMenuItem> = [
    { key: 'edit', label: 'Editar', icon: PencilSquareIcon },
    { key: 'delete', label: 'Eliminar', icon: TrashIcon, tone: 'danger' },
];
const menuItems = computed(() =>
    (props.actions.length ? props.actions : defaultActions).map((action) => ({
        ...action,
        command: () => handleAction(action.key),
    })),
);

function handleAction(key: string): void {
    emit('action', key);
    if (key === 'edit') emit('edit');
    if (key === 'delete') emit('delete');
}
</script>

<template>
    <div class="relative" @click.prevent.stop>
        <Button
            type="button"
            text
            icon-only
            severity="secondary"
            aria-label="Abrir acciones"
            aria-haspopup="menu"
            :aria-controls="menuId"
            :aria-expanded="isOpen"
            class="h-11 w-11 shrink-0"
            @click="menuRef?.toggle($event)"
        >
            <EllipsisHorizontalIcon class="h-5 w-5" />
        </Button>
        <Menu
            :id="menuId"
            ref="menuRef"
            :model="menuItems"
            popup
            @show="isOpen = true"
            @hide="isOpen = false"
        >
            <template #item="{ item, props: itemProps }">
                <a
                    v-bind="itemProps.action"
                    class="flex min-h-11 items-center gap-3"
                    :class="
                        item.tone === 'danger'
                            ? 'text-(--app-color-danger)!'
                            : item.tone === 'success'
                              ? 'text-(--app-color-success)!'
                              : ''
                    "
                >
                    <component :is="item.icon" class="h-4 w-4 shrink-0" />
                    <span>{{ item.label }}</span>
                </a>
            </template>
        </Menu>
    </div>
</template>
