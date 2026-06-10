<script setup lang="ts">
const props = withDefaults(
  defineProps<{
    type?: 'button' | 'submit' | 'reset';
    ariaLabel: string;
  }>(),
  {
    type: 'button',
  },
);
</script>

<template>
  <button
    :type="props.type"
    :aria-label="props.ariaLabel"
    class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-(--app-color-border) text-sm font-semibold text-(--app-color-text) transition hover:bg-(--app-color-surface-muted) focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none disabled:pointer-events-none disabled:opacity-50"
  >
    <slot />
  </button>
</template>
