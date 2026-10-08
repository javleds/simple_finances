<script setup lang="ts" generic="TValue extends string">
type ToggleOption<TOptionValue extends string> = {
  value: TOptionValue;
  label: string;
  disabled?: boolean;
};

const props = defineProps<{
  modelValue: TValue;
  options: ReadonlyArray<ToggleOption<TValue>>;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: TValue];
}>();

function selectValue(option: ToggleOption<TValue>): void {
  if (option.disabled || props.modelValue === option.value) {
    return;
  }

  emit('update:modelValue', option.value);
}
</script>

<template>
  <div
    class="inline-flex rounded-lg border bg-(--app-color-input-bg) p-1"
    :style="{ borderColor: 'var(--app-color-border-strong)' }"
  >
    <button
      v-for="option in props.options"
      :key="option.value"
      type="button"
      :disabled="option.disabled"
      class="rounded-md px-3 py-1.5 text-xs font-semibold transition"
      :class="
        props.modelValue === option.value
          ? 'bg-(--app-color-secondary) text-(--app-color-secondary-foreground)'
          : option.disabled
            ? 'cursor-not-allowed text-(--app-color-text-muted) opacity-50'
            : 'text-(--app-color-text-subtle) hover:text-(--app-color-text)'
      "
      @click="selectValue(option)"
    >
      {{ option.label }}
    </button>
  </div>
</template>
