<script setup lang="ts">
const props = withDefaults(
  defineProps<{
    type?: 'button' | 'submit' | 'reset';
    ariaLabel: string;
    disabled?: boolean;
    loading?: boolean;
  }>(),
  {
    type: 'button',
    disabled: false,
    loading: false,
  },
);
</script>

<template>
  <button
    :type="props.type"
    :aria-label="props.ariaLabel"
    :disabled="props.disabled || props.loading"
    :aria-busy="props.loading ? 'true' : undefined"
    class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-(--app-color-border) text-sm font-semibold text-(--app-color-text) transition hover:bg-(--app-color-surface-muted) focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none disabled:pointer-events-none disabled:opacity-50"
  >
    <span
      v-if="props.loading"
      aria-hidden="true"
      class="h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-current border-t-transparent"
    />
    <slot v-else />
  </button>
</template>
