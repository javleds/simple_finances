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
    class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-[var(--app-color-border)] text-sm font-semibold text-[var(--app-color-text)] transition hover:bg-[var(--app-color-surface-muted)] focus:ring-4 focus:ring-[var(--app-color-focus-ring)] focus:outline-none"
  >
    <slot />
  </button>
</template>
