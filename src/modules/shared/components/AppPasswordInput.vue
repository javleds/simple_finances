<script setup lang="ts">
import { computed, ref } from 'vue';

defineOptions({
  inheritAttrs: false,
});

const props = defineProps<{
  id: string;
  label: string;
  modelValue?: string | number | null;
  error?: string;
  showErrorBeforeBlur?: boolean;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: string];
  blur: [event: FocusEvent];
}>();

const isVisible = ref(false);
const hasBlurred = ref(false);

const inputType = computed(() => {
  return isVisible.value ? 'text' : 'password';
});

const actionLabel = computed(() => {
  return isVisible.value ? 'Ocultar' : 'Mostrar';
});

const visibleError = computed(() => {
  if (!props.error) {
    return undefined;
  }

  return props.showErrorBeforeBlur || hasBlurred.value ? props.error : undefined;
});

function toggleVisibility(): void {
  isVisible.value = !isVisible.value;
}

function handleInput(event: Event): void {
  emit('update:modelValue', (event.target as HTMLInputElement).value);
}

function handleBlur(event: FocusEvent): void {
  hasBlurred.value = true;
  emit('blur', event);
}
</script>

<template>
  <div class="space-y-2.5">
    <div class="flex min-h-5 items-center justify-between gap-4">
      <label
        :for="props.id"
        class="text-sm font-medium"
        :class="visibleError ? 'text-(--app-color-danger)' : 'text-(--app-color-label)'"
      >
        {{ props.label }}
      </label>
      <button
        type="button"
        class="text-sm font-medium text-(--app-color-text-subtle) transition hover:text-(--app-color-link) focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none"
        @click="toggleVisibility"
      >
        {{ actionLabel }}
      </button>
    </div>

    <input
      :id="props.id"
      :type="inputType"
      :value="props.modelValue ?? ''"
      :aria-invalid="Boolean(visibleError)"
      class="h-12 w-full rounded-lg border bg-(--app-color-input-bg) px-4 text-sm text-(--app-color-input-text) transition outline-none placeholder:text-(--app-color-input-placeholder) focus:ring-4 focus:ring-(--app-color-focus-ring)"
      :class="
        visibleError
          ? 'border-(--app-color-danger) focus:border-(--app-color-danger)'
          : 'border-(--app-color-input-border) focus:border-(--app-color-primary)'
      "
      v-bind="$attrs"
      @input="handleInput"
      @blur="handleBlur"
    />
    <p v-if="visibleError" class="text-sm text-(--app-color-danger)">
      {{ visibleError }}
    </p>
  </div>
</template>
