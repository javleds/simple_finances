<script setup lang="ts">
type ToggleOption = {
  value: string;
  label: string;
};

const props = defineProps<{
  modelValue: string;
  options: ReadonlyArray<ToggleOption>;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: string];
}>();

function selectValue(nextTheme: string): void {
  if (props.modelValue === nextTheme) {
    return;
  }

  emit('update:modelValue', nextTheme);
}
</script>

<template>
  <div
    class="inline-flex rounded-lg border bg-[var(--app-color-input-bg)] p-1"
    :style="{ borderColor: 'var(--app-color-border-strong)' }"
  >
    <button
      v-for="option in props.options"
      :key="option.value"
      type="button"
      class="rounded-md px-3 py-1.5 text-xs font-semibold transition"
      :class="
        props.modelValue === option.value
          ? 'bg-[var(--app-color-secondary)] text-[var(--app-color-secondary-foreground)]'
          : 'text-[var(--app-color-text-subtle)] hover:text-[var(--app-color-text)]'
      "
      @click="selectValue(option.value)"
    >
      {{ option.label }}
    </button>
  </div>
</template>
