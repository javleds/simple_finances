<script setup lang="ts" generic="TValue extends string">
type ToggleOption<TOptionValue extends string> = {
  value: TOptionValue;
  label: string;
};

const props = defineProps<{
  modelValue: TValue;
  options: ReadonlyArray<ToggleOption<TValue>>;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: TValue];
}>();

function selectValue(nextValue: TValue): void {
  if (props.modelValue === nextValue) {
    return;
  }

  emit('update:modelValue', nextValue);
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
      class="rounded-md px-3 py-1.5 text-xs font-semibold transition"
      :class="
        props.modelValue === option.value
          ? 'bg-(--app-color-secondary) text-(--app-color-secondary-foreground)'
          : 'text-(--app-color-text-subtle) hover:text-(--app-color-text)'
      "
      @click="selectValue(option.value)"
    >
      {{ option.label }}
    </button>
  </div>
</template>
