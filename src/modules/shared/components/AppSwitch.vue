<script setup lang="ts">
const props = withDefaults(
  defineProps<{
    modelValue: boolean;
    disabled?: boolean;
  }>(),
  {
    disabled: false,
  },
);

const emit = defineEmits<{
  'update:modelValue': [value: boolean];
}>();

function toggleValue(): void {
  if (props.disabled) {
    return;
  }

  emit('update:modelValue', !props.modelValue);
}
</script>

<template>
  <button
    type="button"
    role="switch"
    class="relative inline-flex h-8 w-13 shrink-0 items-center rounded-lg border bg-(--app-color-input-bg) p-1 transition focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none disabled:cursor-not-allowed disabled:opacity-60"
    :style="{ borderColor: 'var(--app-color-border-strong)' }"
    :aria-checked="props.modelValue"
    :disabled="props.disabled"
    @click="toggleValue"
  >
    <span
      class="inline-block h-6 w-5 rounded-md shadow-sm transition"
      :class="
        props.modelValue
          ? 'translate-x-6 bg-(--app-color-secondary)'
          : 'translate-x-0 bg-(--app-color-text)'
      "
    />
  </button>
</template>
