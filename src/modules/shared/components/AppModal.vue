<script setup lang="ts">
import { onBeforeUnmount, watch } from 'vue';

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
      class="fixed inset-0 z-50 flex items-end bg-black/50 p-4 sm:items-center sm:justify-center"
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
          <button
            type="button"
            class="inline-flex h-10 w-10 items-center justify-center rounded-full border text-sm font-semibold text-[var(--app-color-text)] transition hover:bg-[var(--app-color-surface-muted)]"
            :style="{ borderColor: 'var(--app-color-border)' }"
            aria-label="Cerrar modal"
            @click="closeModal"
          >
            X
          </button>
        </header>

        <div class="overflow-y-auto px-4 py-5 sm:px-6">
          <slot />
        </div>

        <footer
          class="flex justify-center border-t px-4 py-4 sm:px-6"
          :style="{ borderColor: 'var(--app-color-border)' }"
        >
          <button
            type="button"
            class="inline-flex h-12 items-center justify-center rounded-lg border px-6 text-sm font-semibold text-[var(--app-color-text)] transition hover:bg-[var(--app-color-surface-muted)] focus:outline-none focus:ring-4 focus:ring-[var(--app-color-focus-ring)]"
            :style="{ borderColor: 'var(--app-color-border-strong)' }"
            @click="closeModal"
          >
            {{ props.closeLabel }}
          </button>
        </footer>
      </div>
    </div>
  </Teleport>
</template>
