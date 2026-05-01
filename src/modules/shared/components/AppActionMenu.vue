<script setup lang="ts">
import type { Component } from 'vue';
import { EllipsisHorizontalIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { nextTick, onBeforeUnmount, ref } from 'vue';

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

const isOpen = ref(false);
const rootRef = ref<HTMLElement | null>(null);
const triggerRef = ref<HTMLButtonElement | null>(null);
const menuRef = ref<HTMLElement | null>(null);
const menuPosition = ref({
  top: 0,
  left: 0,
});

const defaultActions: ReadonlyArray<ActionMenuItem> = [
  {
    key: 'edit',
    label: 'Editar',
    icon: PencilSquareIcon,
    tone: 'default',
  },
  {
    key: 'delete',
    label: 'Eliminar',
    icon: TrashIcon,
    tone: 'danger',
  },
] as const;

const toneClasses = {
  default: 'text-[var(--app-color-text)] hover:bg-[var(--app-color-surface-muted)]',
  danger:
    'text-[var(--app-color-danger)] hover:bg-[color-mix(in_srgb,var(--app-color-danger)_8%,transparent)]',
  success:
    'text-[var(--app-color-success)] hover:bg-[color-mix(in_srgb,var(--app-color-success)_8%,transparent)]',
} as const;

async function toggleMenu(): Promise<void> {
  isOpen.value = !isOpen.value;

  if (!isOpen.value) {
    return;
  }

  await nextTick();
  updateMenuPosition();
}

function closeMenu(): void {
  isOpen.value = false;
}

function handleEdit(): void {
  emit('edit');
  closeMenu();
}

function handleDelete(): void {
  emit('delete');
  closeMenu();
}

function resolvedActions(): ReadonlyArray<ActionMenuItem> {
  if (props.actions.length > 0) {
    return props.actions;
  }

  return defaultActions;
}

function handleAction(actionKey: string): void {
  emit('action', actionKey);

  if (actionKey === 'edit') {
    handleEdit();
    return;
  }

  if (actionKey === 'delete') {
    handleDelete();
    return;
  }

  closeMenu();
}

function updateMenuPosition(): void {
  if (!triggerRef.value) {
    return;
  }

  const triggerBounds = triggerRef.value.getBoundingClientRect();
  const menuWidth = 160;
  const viewportPadding = 12;
  const calculatedLeft = triggerBounds.right - menuWidth;
  const maxLeft = window.innerWidth - menuWidth - viewportPadding;

  menuPosition.value = {
    top: triggerBounds.bottom + 8,
    left: Math.max(viewportPadding, Math.min(calculatedLeft, maxLeft)),
  };
}

function handleDocumentPointerDown(event: PointerEvent): void {
  const eventTarget = event.target as Node | null;

  if (!eventTarget) {
    return;
  }

  if (rootRef.value?.contains(eventTarget)) {
    return;
  }

  if (menuRef.value?.contains(eventTarget)) {
    return;
  }

  closeMenu();
}

function handleEscapeKey(event: KeyboardEvent): void {
  if (event.key !== 'Escape') {
    return;
  }

  closeMenu();
}

function handleWindowResize(): void {
  if (!isOpen.value) {
    return;
  }

  updateMenuPosition();
}

if (typeof document !== 'undefined') {
  document.addEventListener('pointerdown', handleDocumentPointerDown);
  document.addEventListener('keydown', handleEscapeKey);
  window.addEventListener('resize', handleWindowResize);
}

onBeforeUnmount(() => {
  if (typeof document === 'undefined') {
    return;
  }

  document.removeEventListener('pointerdown', handleDocumentPointerDown);
  document.removeEventListener('keydown', handleEscapeKey);
  window.removeEventListener('resize', handleWindowResize);
});
</script>

<template>
  <div ref="rootRef" class="relative" @click.prevent.stop>
    <button
      ref="triggerRef"
      type="button"
      class="inline-flex h-6 items-center justify-center rounded-md px-1 text-[var(--app-color-text-subtle)] transition hover:text-[var(--app-color-text)] focus:ring-4 focus:ring-[var(--app-color-focus-ring)] focus:outline-none"
      aria-label="Abrir acciones"
      @click.prevent.stop="toggleMenu"
    >
      <EllipsisHorizontalIcon class="h-5 w-5" />
    </button>

    <Teleport to="body">
      <div
        v-if="isOpen"
        ref="menuRef"
        class="fixed z-50 min-w-40 overflow-hidden rounded-xl border bg-[var(--app-color-surface)] shadow-[var(--app-shadow-card)]"
        :style="{
          top: `${menuPosition.top}px`,
          left: `${menuPosition.left}px`,
          borderColor: 'var(--app-color-border)',
        }"
        @click.stop
        @pointerdown.stop
      >
        <button
          v-for="(action, index) in resolvedActions()"
          :key="action.key"
          type="button"
          class="flex w-full items-center gap-3 px-4 py-3 text-left text-sm font-medium transition focus:ring-4 focus:ring-[var(--app-color-focus-ring)] focus:outline-none"
          :class="[
            toneClasses[action.tone ?? 'default'],
            index < resolvedActions().length - 1 ? 'border-b' : '',
          ]"
          :style="{ borderColor: 'var(--app-color-border)' }"
          @click="handleAction(action.key)"
        >
          <component :is="action.icon" class="h-4 w-4 shrink-0" />
          <span>{{ action.label }}</span>
        </button>
      </div>
    </Teleport>
  </div>
</template>
