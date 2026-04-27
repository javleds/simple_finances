<script setup lang="ts">
const props = withDefaults(
  defineProps<{
    as?: 'h1' | 'h2' | 'h3' | 'h4';
    size?: 'sm' | 'md' | 'lg';
  }>(),
  {
    as: 'h2',
    size: 'md',
  },
);

const sizeClasses = {
  sm: 'text-xl sm:text-2xl',
  md: 'text-2xl sm:text-3xl',
  lg: 'text-3xl sm:text-4xl',
} as const;
</script>

<template>
  <component
    :is="props.as"
    class="font-semibold tracking-tight text-[var(--app-color-text)]"
    :class="sizeClasses[props.size]"
  >
    <slot />
  </component>
</template>
