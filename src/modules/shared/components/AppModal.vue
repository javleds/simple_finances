<script setup lang="ts">
import { onBeforeUnmount, watch } from 'vue';

import AppButton from './AppButton.vue';
import AppIconButton from './AppIconButton.vue';

const props = withDefaults(
  defineProps<{
    open: boolean;
    title: string;
    closeLabel?: string;
  }>(),
  {
    closeLabel: 'Cerrar',
  },
);

const emit = defineEmits<{
  close: [];
}>();

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
</script>

<template>
  <Teleport to="body">
    <div
      v-if="props.open"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      @click.self="closeModal"
    >
      <div
        class="flex max-h-[90vh] w-full max-w-xl flex-col overflow-hidden rounded-2xl border bg-[var(--app-color-surface)] shadow-[var(--app-shadow-card)]"
        :style="{ borderColor: 'var(--app-color-border)' }"
        role="dialog"
        aria-modal="true"
        :aria-label="props.title"
      >
        <header
          class="flex items-center justify-between gap-4 border-b px-4 py-4 sm:px-6"
          :style="{ borderColor: 'var(--app-color-border)' }"
        >
          <h2
            class="flex-1 text-left text-base font-semibold text-[var(--app-color-text)] sm:text-lg"
          >
            {{ props.title }}
          </h2>
          <AppIconButton ariaLabel="Cerrar modal" @click="closeModal">
            X
          </AppIconButton>
        </header>

        <div class="overflow-y-auto px-4 py-5 sm:px-6">
          <slot />
        </div>

        <footer
          class="flex justify-center border-t px-4 py-4 sm:px-6"
          :style="{ borderColor: 'var(--app-color-border)' }"
        >
          <AppButton type="button" variant="outline" class="px-6" @click="closeModal">
            {{ props.closeLabel }}
          </AppButton>
        </footer>
      </div>
    </div>
  </Teleport>
</template>
