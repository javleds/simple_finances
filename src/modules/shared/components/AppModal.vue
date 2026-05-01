<script setup lang="ts">
import type { Component } from 'vue';
import {
  CheckCircleIcon,
  ExclamationCircleIcon,
  ExclamationTriangleIcon,
  InformationCircleIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline';
import { computed, onBeforeUnmount, watch } from 'vue';

import AppIconButton from './AppIconButton.vue';
import AppTitle from './AppTitle.vue';

type ModalVariant = 'default' | 'warning' | 'danger' | 'success';
type ModalActionTone = 'primary' | 'danger' | 'neutral';

type ModalAction = {
  key: string;
  label: string;
  tone?: ModalActionTone;
  icon?: Component;
  type?: 'button' | 'submit' | 'reset';
  disabled?: boolean;
  form?: string;
  autoClose?: boolean;
};

const props = withDefaults(
  defineProps<{
    open: boolean;
    title: string;
    variant?: ModalVariant;
    actions?: ReadonlyArray<ModalAction>;
    closeLabel?: string;
  }>(),
  {
    variant: 'default',
    actions: () => [],
    closeLabel: 'Cerrar',
  },
);

const emit = defineEmits<{
  close: [];
  action: [key: string];
}>();

const modalVariantStyles = {
  default: {
    headerClass: 'bg-[color-mix(in_srgb,var(--app-color-primary)_10%,var(--app-color-surface))]',
    icon: InformationCircleIcon,
    iconStyle: {
      backgroundColor: 'color-mix(in srgb, var(--app-color-primary) 16%, transparent)',
      color: 'var(--app-color-primary)',
    },
  },
  warning: {
    headerClass: 'bg-[color-mix(in_srgb,var(--app-color-warning)_10%,var(--app-color-surface))]',
    icon: ExclamationTriangleIcon,
    iconStyle: {
      backgroundColor: 'color-mix(in srgb, var(--app-color-warning) 16%, transparent)',
      color: 'var(--app-color-warning)',
    },
  },
  danger: {
    headerClass: 'bg-[color-mix(in_srgb,var(--app-color-danger)_10%,var(--app-color-surface))]',
    icon: ExclamationCircleIcon,
    iconStyle: {
      backgroundColor: 'color-mix(in srgb, var(--app-color-danger) 16%, transparent)',
      color: 'var(--app-color-danger)',
    },
  },
  success: {
    headerClass: 'bg-[color-mix(in_srgb,var(--app-color-success)_10%,var(--app-color-surface))]',
    icon: CheckCircleIcon,
    iconStyle: {
      backgroundColor: 'color-mix(in srgb, var(--app-color-success) 16%, transparent)',
      color: 'var(--app-color-success)',
    },
  },
} as const;

const actionToneStyles = {
  primary: {
    buttonClass:
      'text-(--app-color-primary) hover:bg-[color-mix(in_srgb,var(--app-color-primary)_8%,transparent)]',
    barStyle: {
      backgroundColor: 'var(--app-color-primary)',
    },
  },
  danger: {
    buttonClass:
      'text-(--app-color-danger) hover:bg-[color-mix(in_srgb,var(--app-color-danger)_8%,transparent)]',
    barStyle: {
      backgroundColor: 'var(--app-color-danger)',
    },
  },
  neutral: {
    buttonClass: 'text-(--app-color-text) hover:bg-(--app-color-surface-muted)',
    barStyle: {
      backgroundColor: 'var(--app-color-border-strong)',
    },
  },
} as const;

const modalStyle = computed(() => modalVariantStyles[props.variant]);

const resolvedActions = computed<ReadonlyArray<ModalAction>>(() => {
  if (props.actions.length > 0) {
    const limitedActions = props.actions.slice(0, 3);
    const closeActionIndex = limitedActions.findIndex((action) => isCloseAction(action));

    if (closeActionIndex <= 0) {
      return limitedActions;
    }

    const closeAction = limitedActions[closeActionIndex];

    if (!closeAction) {
      return limitedActions;
    }

    const remainingActions = limitedActions.filter((_, index) => index !== closeActionIndex);

    return [closeAction, ...remainingActions];
  }

  return [
    {
      key: 'close',
      label: props.closeLabel,
      tone: 'danger',
      autoClose: true,
    },
  ];
});

watch(
  () => props.open,
  (isOpen) => {
    if (typeof document === 'undefined') {
      return;
    }

    document.body.style.overflow = isOpen ? 'hidden' : '';
  },
  { immediate: true },
);

onBeforeUnmount(() => {
  if (typeof document === 'undefined') {
    return;
  }

  document.body.style.overflow = '';
});

function closeModal(): void {
  emit('close');
}

function handleAction(action: ModalAction): void {
  emit('action', action.key);

  if (action.autoClose) {
    closeModal();
  }
}

function isCloseAction(action: ModalAction): boolean {
  const normalizedKey = action.key.trim().toLowerCase();

  if (normalizedKey === 'close' || normalizedKey === 'cancel') {
    return true;
  }

  return action.autoClose === true;
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="props.open"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      @click.self="closeModal"
    >
      <div
        class="flex max-h-[90vh] w-full max-w-xl flex-col overflow-hidden rounded-2xl border bg-(--app-color-surface) shadow-(--app-shadow-card)"
        :style="{ borderColor: 'var(--app-color-border)' }"
        role="dialog"
        aria-modal="true"
        :aria-label="props.title"
      >
        <header
          class="flex items-start justify-between gap-4 border-b px-4 py-4 sm:px-6"
          :class="modalStyle.headerClass"
          :style="{ borderColor: 'var(--app-color-border)' }"
        >
          <div class="flex min-w-0 flex-1 items-center gap-3">
            <div
              v-if="modalStyle.icon"
              class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
              :style="modalStyle.iconStyle"
            >
              <component :is="modalStyle.icon" class="h-5 w-5" />
            </div>

            <AppTitle as="h2" size="sm" class="min-w-0 flex-1 text-left !text-base sm:!text-lg">
              {{ props.title }}
            </AppTitle>
          </div>

          <AppIconButton ariaLabel="Cerrar modal" @click="closeModal">
            <XMarkIcon class="h-5 w-5" />
          </AppIconButton>
        </header>

        <div class="min-h-0 flex-1 overflow-y-auto px-4 py-5 sm:px-6">
          <slot />
        </div>

        <footer class="flex shrink-0 border-t" :style="{ borderColor: 'var(--app-color-border)' }">
          <button
            v-for="action in resolvedActions"
            :key="action.key"
            :type="action.type ?? 'button'"
            :form="action.form"
            :disabled="action.disabled"
            class="relative flex min-w-0 flex-1 items-center justify-center gap-3 border-r px-4 py-4 text-sm font-semibold transition last:border-r-0 focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
            :class="actionToneStyles[action.tone ?? 'primary'].buttonClass"
            :style="{ borderColor: 'var(--app-color-border)' }"
            @click="handleAction(action)"
          >
            <component :is="action.icon" v-if="action.icon" class="h-5 w-5 shrink-0" />
            <span>{{ action.label }}</span>
            <span
              aria-hidden="true"
              class="pointer-events-none absolute inset-x-0 bottom-0 h-1"
              :style="actionToneStyles[action.tone ?? 'primary'].barStyle"
            />
          </button>
        </footer>
      </div>
    </div>
  </Teleport>
</template>
