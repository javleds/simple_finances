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
    'bg-(--app-color-primary) text-(--app-color-primary-foreground) hover:bg-(--app-color-primary-hover)',
  secondary:
    'bg-(--app-color-secondary) text-(--app-color-secondary-foreground) hover:bg-(--app-color-secondary-hover)',
  ghost:
    'bg-transparent text-(--app-color-link) hover:bg-[color-mix(in_srgb,var(--app-color-link)_8%,transparent)]',
  outline:
    'border border-(--app-color-border-strong) bg-transparent text-(--app-color-text) hover:bg-(--app-color-surface-muted)',
} as const;
</script>

<template>
  <button
    :type="props.type"
    class="inline-flex h-12 items-center justify-center rounded-lg px-4 text-sm font-semibold transition focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none"
    :class="[variantClasses[props.variant], props.fullWidth ? 'w-full' : '']"
  >
    <slot />
  </button>
</template>
