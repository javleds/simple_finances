<script setup lang="ts">
const props = withDefaults(
  defineProps<{
    type?: 'button' | 'submit' | 'reset';
    variant?: 'primary' | 'secondary' | 'ghost' | 'outline';
    fullWidth?: boolean;
  }>(),
  {
    type: 'button',
    variant: 'primary',
    fullWidth: false,
  },
);

const variantClasses = {
  primary:
    'bg-[var(--app-color-primary)] text-[var(--app-color-primary-foreground)] hover:bg-[var(--app-color-primary-hover)]',
  secondary:
    'bg-[var(--app-color-secondary)] text-[var(--app-color-secondary-foreground)] hover:bg-[var(--app-color-secondary-hover)]',
  ghost:
    'bg-transparent text-[var(--app-color-link)] hover:bg-[color-mix(in_srgb,var(--app-color-link)_8%,transparent)]',
  outline:
    'border border-[var(--app-color-border-strong)] bg-transparent text-[var(--app-color-text)] hover:bg-[var(--app-color-surface-muted)]',
} as const;
</script>

<template>
  <button
    :type="props.type"
    class="inline-flex h-12 items-center justify-center rounded-lg px-4 text-sm font-semibold transition focus:outline-none focus:ring-4 focus:ring-[var(--app-color-focus-ring)]"
    :class="[variantClasses[props.variant], props.fullWidth ? 'w-full' : '']"
  >
    <slot />
  </button>
</template>
