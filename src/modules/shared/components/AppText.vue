<script setup lang="ts">
const props = withDefaults(
  defineProps<{
    as?: 'p' | 'span' | 'div';
    tone?: 'default' | 'muted' | 'subtle';
    size?: 'sm' | 'md';
  }>(),
  {
    as: 'p',
    tone: 'default',
    size: 'md',
  },
);

const toneClasses = {
  default: 'text-[var(--app-color-text-muted)]',
  muted: 'text-[var(--app-color-label)]',
  subtle: 'text-[var(--app-color-text-subtle)]',
} as const;

const sizeClasses = {
  sm: 'text-sm leading-5',
  md: 'text-sm leading-6',
} as const;
</script>

<template>
  <component :is="props.as" :class="[toneClasses[props.tone], sizeClasses[props.size]]">
    <slot />
  </component>
</template>
