<script setup lang="ts">
defineOptions({
  inheritAttrs: false,
});

const props = withDefaults(
  defineProps<{
    id: string;
    label?: string;
    type?: string;
    modelValue?: string | number | null;
    error?: string;
  }>(),
  {
    label: undefined,
    type: 'text',
    modelValue: undefined,
    error: undefined,
  },
);

const emit = defineEmits<{
  'update:modelValue': [value: string];
  blur: [event: FocusEvent];
}>();

function handleInput(event: Event): void {
  emit('update:modelValue', (event.target as HTMLInputElement).value);
}

function handleBlur(event: FocusEvent): void {
  emit('blur', event);
}
</script>

<template>
  <div class="space-y-2.5">
    <div v-if="props.label" class="flex min-h-5 items-center">
      <label
        :for="props.id"
        class="text-sm font-medium"
        :class="props.error ? 'text-(--app-color-danger)' : 'text-(--app-color-label)'"
      >
        {{ props.label }}
      </label>
    </div>
    <input
      :id="props.id"
      :type="props.type"
      :value="props.modelValue ?? ''"
      :aria-invalid="Boolean(props.error)"
      class="h-12 w-full rounded-lg border bg-(--app-color-input-bg) px-4 text-sm text-(--app-color-input-text) transition outline-none placeholder:text-(--app-color-input-placeholder) focus:ring-4 focus:ring-(--app-color-focus-ring)"
      :class="
        props.error
          ? 'border-(--app-color-danger) focus:border-(--app-color-danger)'
          : 'border-(--app-color-input-border) focus:border-(--app-color-primary)'
      "
      v-bind="$attrs"
      @input="handleInput"
      @blur="handleBlur"
    />
    <p v-if="props.error" class="text-sm text-(--app-color-danger)">
      {{ props.error }}
    </p>
  </div>
</template>
