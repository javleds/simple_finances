<script setup lang="ts">
import type { Component } from 'vue';

type ContextTabOption = {
  value: string;
  label: string;
  icon?: Component;
};

const props = withDefaults(
  defineProps<{
    modelValue: string;
    options: ReadonlyArray<ContextTabOption>;
    indicatorPosition?: 'top' | 'bottom';
  }>(),
  {
    indicatorPosition: 'bottom',
  },
);

const emit = defineEmits<{
  'update:modelValue': [value: string];
}>();

function selectTab(nextValue: string): void {
  if (props.modelValue === nextValue) {
    return;
  }

  emit('update:modelValue', nextValue);
}

function tabClasses(optionValue: string): string {
  if (props.modelValue === optionValue) {
    return 'text-[var(--app-color-text)] after:scale-x-100 after:opacity-100';
  }

  return 'text-[var(--app-color-text-subtle)] after:scale-x-0 after:opacity-0 hover:text-[var(--app-color-text)]';
}

function indicatorPositionClasses(): string {
  if (props.indicatorPosition === 'top') {
    return 'after:top-0';
  }

  return 'after:bottom-0';
}
</script>

<template>
  <div class="flex overflow-x-auto px-1">
    <button
      v-for="option in props.options"
      :key="option.value"
      type="button"
      class="relative inline-flex shrink-0 items-center gap-2 px-3 py-3 text-sm font-semibold transition after:absolute after:right-2 after:left-2 after:h-1 after:origin-center after:rounded-full after:bg-[var(--app-color-primary)] after:shadow-[0_0_18px_rgba(29,78,216,0.45)] after:transition-all focus:outline-none"
      :class="[tabClasses(option.value), indicatorPositionClasses()]"
      @click="selectTab(option.value)"
    >
      <component :is="option.icon" v-if="option.icon" class="h-4 w-4" />
      <span>{{ option.label }}</span>
    </button>
  </div>
</template>
