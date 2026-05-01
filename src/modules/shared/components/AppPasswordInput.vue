<script setup lang="ts">
import { computed, ref } from 'vue';

defineOptions({
  inheritAttrs: false,
});

const props = defineProps<{
  id: string;
  label: string;
  modelValue?: string | number | null;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: string];
}>();

const isVisible = ref(false);

const inputType = computed(() => {
  return isVisible.value ? 'text' : 'password';
});

const actionLabel = computed(() => {
  return isVisible.value ? 'Ocultar' : 'Mostrar';
});

function toggleVisibility(): void {
  isVisible.value = !isVisible.value;
}

function handleInput(event: Event): void {
  emit('update:modelValue', (event.target as HTMLInputElement).value);
}
</script>

<template>
  <div class="space-y-2.5">
    <div class="flex min-h-5 items-center justify-between gap-4">
      <label :for="props.id" class="text-sm font-medium text-[var(--app-color-label)]">
        {{ props.label }}
      </label>
      <button
        type="button"
        class="text-sm font-medium text-[var(--app-color-text-subtle)] transition hover:text-[var(--app-color-link)] focus:outline-none focus:ring-4 focus:ring-[var(--app-color-focus-ring)]"
        @click="toggleVisibility"
      >
        {{ actionLabel }}
      </button>
    </div>

    <input
      :id="props.id"
      :type="inputType"
      :value="props.modelValue ?? ''"
      class="h-12 w-full rounded-lg border border-[var(--app-color-input-border)] bg-[var(--app-color-input-bg)] px-4 text-sm text-[var(--app-color-input-text)] outline-none placeholder:text-[var(--app-color-input-placeholder)] transition focus:border-[var(--app-color-primary)] focus:ring-4 focus:ring-[var(--app-color-focus-ring)]"
      v-bind="$attrs"
      @input="handleInput"
    />
  </div>
</template>
