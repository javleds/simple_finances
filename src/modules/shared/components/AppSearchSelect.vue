<script setup lang="ts">
import {
  ChevronDownIcon,
  MagnifyingGlassIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline';
import Multiselect from '@vueform/multiselect';

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

function updateValue(nextValue: unknown): void {
  emit('update:modelValue', typeof nextValue === 'string' ? nextValue : null);
}
</script>

<template>
  <div class="app-search-select space-y-2.5">
    <div v-if="props.label" class="flex min-h-5 items-center">
      <label :for="props.id" class="text-sm font-medium text-(--app-color-label)">
        {{ props.label }}
      </label>
    </div>

    <Multiselect
      :id="props.id"
      :model-value="props.modelValue"
      mode="single"
      :options="props.options as SearchSelectOption[]"
      value-prop="value"
      label="label"
      track-by="label"
      :searchable="true"
      :placeholder="props.placeholder"
      :disabled="props.disabled"
      :append-to-body="true"
      :close-on-select="true"
      :clear-on-search="false"
      :clear-on-select="false"
      :clear-on-blur="false"
      :can-clear="true"
      :can-deselect="true"
      :no-options-text="props.emptyMessage"
      :no-results-text="props.emptyMessage"
      @update:model-value="updateValue"
    >
      <template #caret>
        <ChevronDownIcon class="h-5 w-5 text-(--app-color-text-subtle)" />
      </template>

      <template #clear="{ clear }">
        <button
          type="button"
          class="flex items-center text-(--app-color-text-subtle) transition hover:text-(--app-color-text)"
          aria-label="Limpiar selección"
          @mousedown.prevent
          @click.prevent="clear"
        >
          <XMarkIcon class="h-4 w-4" />
        </button>
      </template>

      <template #singlelabel="{ value }">
        <span class="block max-w-full truncate text-sm text-(--app-color-input-text)">
          {{ value.label }}
        </span>
      </template>

      <template #option="{ option }">
        <div class="min-w-0 space-y-0.5">
          <p class="truncate text-sm font-semibold text-(--app-color-text)">
            {{ option.label }}
          </p>
          <p
            v-if="option.description"
            class="line-clamp-2 text-xs leading-5 text-(--app-color-text-subtle)"
          >
            {{ option.description }}
          </p>
        </div>
      </template>

      <template #nooptions>
        <div class="px-3 py-4 text-center text-sm text-(--app-color-text-subtle)">
          {{ props.emptyMessage }}
        </div>
      </template>

      <template #noresults>
        <div class="px-3 py-4 text-center text-sm text-(--app-color-text-subtle)">
          {{ props.emptyMessage }}
        </div>
      </template>
    </Multiselect>

    <div class="pointer-events-none relative -mt-[3.125rem] ml-3 h-0">
      <MagnifyingGlassIcon class="h-4 w-4 text-(--app-color-text-subtle)" />
    </div>
  </div>
</template>

<style scoped>
.app-search-select {
  --ms-font-size: 0.875rem;
  --ms-line-height: 1.25rem;
  --ms-bg: var(--app-color-input-bg);
  --ms-bg-disabled: var(--app-color-surface-muted);
  --ms-border-color: var(--app-color-input-border);
  --ms-border-color-active: var(--app-color-primary);
  --ms-border-width: 1px;
  --ms-border-width-active: 1px;
  --ms-radius: 0.5rem;
  --ms-py: 0.75rem;
  --ms-px: 0.875rem;
  --ms-placeholder-color: var(--app-color-input-placeholder);
  --ms-color: var(--app-color-input-text);
  --ms-caret-color: var(--app-color-text-subtle);
  --ms-clear-color: var(--app-color-text-subtle);
  --ms-clear-color-hover: var(--app-color-text);
  --ms-spinner-color: var(--app-color-primary);
  --ms-ring-width: 4px;
  --ms-ring-color: var(--app-color-focus-ring);
  --ms-dropdown-bg: var(--app-color-surface);
  --ms-dropdown-border-color: var(--app-color-border);
  --ms-dropdown-border-width: 1px;
  --ms-dropdown-radius: 0.75rem;
  --ms-option-font-size: 0.875rem;
  --ms-option-line-height: 1.25rem;
  --ms-option-py: 0.75rem;
  --ms-option-px: 0.875rem;
  --ms-option-bg-pointed: var(--app-color-surface-muted);
  --ms-option-color-pointed: var(--app-color-text);
  --ms-option-bg-selected: color-mix(in srgb, var(--app-color-primary) 10%, transparent);
  --ms-option-color-selected: var(--app-color-text);
  --ms-option-bg-selected-pointed: color-mix(in srgb, var(--app-color-primary) 14%, transparent);
  --ms-option-color-selected-pointed: var(--app-color-text);
  --ms-empty-color: var(--app-color-text-subtle);
  --ms-max-height: 14rem;
}

.app-search-select :deep(.multiselect) {
  min-height: 3rem;
  box-shadow: none;
}

.app-search-select :deep(.multiselect.is-active) {
  box-shadow: 0 0 0 4px var(--app-color-focus-ring);
}

.app-search-select :deep(.multiselect-placeholder),
.app-search-select :deep(.multiselect-single-label) {
  padding-left: 2.35rem;
}

.app-search-select :deep(.multiselect-search) {
  padding-left: 2.35rem;
  color: var(--app-color-input-text);
}

.app-search-select :deep(.multiselect-search::placeholder) {
  color: var(--app-color-input-placeholder);
}

.app-search-select :deep(.multiselect-caret) {
  background: none;
  width: 1.25rem;
  height: 1.25rem;
  margin-right: 0.75rem;
}

.app-search-select :deep(.multiselect-clear) {
  padding-right: 0.5rem;
}

.app-search-select :deep(.multiselect-option) {
  border-radius: 0.5rem;
  margin: 0.125rem 0.5rem;
}

.app-search-select :deep(.multiselect-dropdown) {
  margin-top: 0.5rem;
  box-shadow: var(--app-shadow-card);
  overflow-x: hidden;
}
</style>
