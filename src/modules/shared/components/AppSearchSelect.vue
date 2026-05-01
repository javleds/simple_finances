<script setup lang="ts">
import {
  CheckIcon,
  ChevronDownIcon,
  MagnifyingGlassIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

type SearchSelectOption = {
  value: string;
  label: string;
  description?: string;
};

const props = withDefaults(
  defineProps<{
    id: string;
    modelValue: string | null;
    options: ReadonlyArray<SearchSelectOption>;
    label?: string;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyMessage?: string;
    disabled?: boolean;
  }>(),
  {
    label: undefined,
    placeholder: 'Seleccionar opción',
    searchPlaceholder: 'Buscar opción',
    emptyMessage: 'No hay resultados disponibles.',
    disabled: false,
  },
);

const emit = defineEmits<{
  'update:modelValue': [value: string | null];
}>();

const isOpen = ref(false);
const searchTerm = ref('');
const rootRef = ref<HTMLElement | null>(null);
const searchInputRef = ref<HTMLInputElement | null>(null);

const selectedOption = computed(() => {
  if (!props.modelValue) {
    return null;
  }

  return props.options.find((option) => option.value === props.modelValue) ?? null;
});

const filteredOptions = computed(() => {
  const normalizedQuery = searchTerm.value.trim().toLowerCase();

  if (!normalizedQuery) {
    return props.options;
  }

  return props.options.filter((option) => {
    const matchesLabel = option.label.toLowerCase().includes(normalizedQuery);
    const matchesDescription = option.description?.toLowerCase().includes(normalizedQuery) ?? false;

    return matchesLabel || matchesDescription;
  });
});

watch(isOpen, async (nextIsOpen) => {
  if (!nextIsOpen) {
    searchTerm.value = '';
    return;
  }

  await nextTick();
  searchInputRef.value?.focus();
});

function toggleOptions(): void {
  if (props.disabled) {
    return;
  }

  isOpen.value = !isOpen.value;
}

function closeOptions(): void {
  isOpen.value = false;
}

function selectOption(optionValue: string): void {
  emit('update:modelValue', optionValue);
  closeOptions();
}

function clearSelection(): void {
  emit('update:modelValue', null);
  closeOptions();
}

function handleDocumentPointerDown(event: PointerEvent): void {
  if (!rootRef.value) {
    return;
  }

  if (rootRef.value.contains(event.target as Node)) {
    return;
  }

  closeOptions();
}

function handleEscapeKey(event: KeyboardEvent): void {
  if (event.key !== 'Escape') {
    return;
  }

  closeOptions();
}

if (typeof document !== 'undefined') {
  document.addEventListener('pointerdown', handleDocumentPointerDown);
  document.addEventListener('keydown', handleEscapeKey);
}

onBeforeUnmount(() => {
  if (typeof document === 'undefined') {
    return;
  }

  document.removeEventListener('pointerdown', handleDocumentPointerDown);
  document.removeEventListener('keydown', handleEscapeKey);
});
</script>

<template>
  <div ref="rootRef" class="space-y-2.5">
    <div v-if="props.label" class="flex min-h-5 items-center">
      <label :for="props.id" class="text-sm font-medium text-(--app-color-label)">
        {{ props.label }}
      </label>
    </div>

    <div class="relative">
      <button
        :id="props.id"
        type="button"
        class="flex h-12 w-full items-center justify-between gap-3 rounded-lg border border-(--app-color-input-border) bg-(--app-color-input-bg) px-4 text-left text-sm text-(--app-color-input-text) transition focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none disabled:cursor-not-allowed disabled:opacity-60"
        :class="isOpen ? 'border-(--app-color-primary)' : ''"
        :disabled="props.disabled"
        :aria-expanded="isOpen"
        aria-haspopup="listbox"
        @click="toggleOptions"
      >
        <span
          class="min-w-0 flex-1 truncate"
          :class="
            selectedOption
              ? 'text-(--app-color-input-text)'
              : 'text-(--app-color-input-placeholder)'
          "
        >
          {{ selectedOption?.label ?? props.placeholder }}
        </span>

        <ChevronDownIcon
          class="h-5 w-5 shrink-0 text-(--app-color-text-subtle) transition"
          :class="isOpen ? 'rotate-180' : ''"
        />
      </button>

      <div
        v-if="isOpen"
        class="absolute inset-x-0 top-[calc(100%+0.5rem)] z-20 space-y-3 rounded-xl border bg-(--app-color-surface) p-3 shadow-(--app-shadow-card)"
        :style="{ borderColor: 'var(--app-color-border)' }"
      >
        <div class="relative">
          <div
            class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-(--app-color-text-subtle)"
          >
            <MagnifyingGlassIcon class="h-4 w-4" />
          </div>

          <input
            ref="searchInputRef"
            type="text"
            :placeholder="props.searchPlaceholder"
            :value="searchTerm"
            class="h-11 w-full rounded-lg border border-(--app-color-input-border) bg-(--app-color-input-bg) pr-10 pl-9 text-sm text-(--app-color-input-text) transition outline-none placeholder:text-(--app-color-input-placeholder) focus:border-(--app-color-primary) focus:ring-4 focus:ring-(--app-color-focus-ring)"
            @input="searchTerm = ($event.target as HTMLInputElement).value"
          />

          <button
            v-if="props.modelValue"
            type="button"
            class="absolute inset-y-0 right-2 flex items-center text-(--app-color-text-subtle) transition hover:text-(--app-color-text)"
            aria-label="Limpiar selección"
            @click="clearSelection"
          >
            <XMarkIcon class="h-4 w-4" />
          </button>
        </div>

        <div class="max-h-56 overflow-y-auto">
          <div v-if="filteredOptions.length === 0" class="rounded-lg px-3 py-4 text-center">
            <p class="text-sm text-(--app-color-text-subtle)">
              {{ props.emptyMessage }}
            </p>
          </div>

          <div v-else class="space-y-2">
            <button
              v-for="option in filteredOptions"
              :key="option.value"
              type="button"
              class="flex w-full items-start justify-between gap-3 rounded-lg border px-3 py-3 text-left transition focus:ring-4 focus:ring-(--app-color-focus-ring) focus:outline-none"
              :class="
                props.modelValue === option.value
                  ? 'bg-(--app-color-surface-muted) text-(--app-color-text)'
                  : 'bg-transparent text-(--app-color-text-subtle) hover:bg-(--app-color-surface-muted) hover:text-(--app-color-text)'
              "
              :style="{ borderColor: 'var(--app-color-border)' }"
              @click="selectOption(option.value)"
            >
              <div class="min-w-0 flex-1 space-y-1">
                <p class="truncate text-sm font-semibold">{{ option.label }}</p>
                <p
                  v-if="option.description"
                  class="text-sm leading-5 text-(--app-color-text-subtle)"
                >
                  {{ option.description }}
                </p>
              </div>

              <CheckIcon
                v-if="props.modelValue === option.value"
                class="mt-0.5 h-5 w-5 shrink-0 text-(--app-color-primary)"
              />
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
