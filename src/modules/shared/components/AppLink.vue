<script setup lang="ts">
import { computed } from 'vue';
import { RouterLink } from 'vue-router';

const props = withDefaults(
  defineProps<{
    href?: string;
    to?: string | Record<string, unknown>;
    variant?: 'primary' | 'secondary' | 'subtle';
  }>(),
  {
    href: undefined,
    to: undefined,
    variant: 'primary',
  },
);

const componentTag = computed(() => {
  return props.to ? RouterLink : 'a';
});

const componentProps = computed(() => {
  if (props.to) {
    return { to: props.to };
  }

  return { href: props.href ?? '' };
});

const variantClasses = {
  primary: 'text-[var(--app-color-link)] hover:text-[var(--app-color-link-hover)]',
  secondary: 'text-[var(--app-color-text)] hover:text-[var(--app-color-link)]',
  subtle: 'text-[var(--app-color-text-subtle)] hover:text-[var(--app-color-link)]',
} as const;
</script>

<template>
  <component
    :is="componentTag"
    v-bind="componentProps"
    class="font-medium transition focus:ring-4 focus:ring-[var(--app-color-focus-ring)] focus:outline-none"
    :class="variantClasses[props.variant]"
  >
    <slot />
  </component>
</template>
