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
  }>(),
  {
    label: undefined,
    type: 'text',
    modelValue: undefined,
  },
);

const emit = defineEmits<{
  'update:modelValue': [value: string];
}>();

function handleInput(event: Event): void {
  emit('update:modelValue', (event.target as HTMLInputElement).value);
}
</script>

<template>
  <div class="space-y-2.5">
    <div v-if="props.label" class="flex min-h-5 items-center">
      <label :for="props.id" class="text-sm font-medium text-[var(--app-color-label)]">
        {{ props.label }}
      </label>
    </div>
    <input
      :id="props.id"
      :type="props.type"
      :value="props.modelValue ?? ''"
      class="h-12 w-full rounded-lg border border-[var(--app-color-input-border)] bg-[var(--app-color-input-bg)] px-4 text-sm text-[var(--app-color-input-text)] outline-none placeholder:text-[var(--app-color-input-placeholder)] transition focus:border-[var(--app-color-primary)] focus:ring-4 focus:ring-[var(--app-color-focus-ring)]"
      v-bind="$attrs"
      @input="handleInput"
    />
  </div>
</template>
