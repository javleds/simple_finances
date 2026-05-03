<script setup lang="ts">
import { CalendarDaysIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { computed } from 'vue';
import { VueDatePicker } from '@vuepic/vue-datepicker';

defineOptions({
  inheritAttrs: false,
});

const props = withDefaults(
  defineProps<{
    id: string;
    modelValue?: string | null;
    label?: string;
    placeholder?: string;
    disabled?: boolean;
    clearable?: boolean;
    minDate?: Date | string | number;
    maxDate?: Date | string | number;
  }>(),
  {
    modelValue: null,
    label: undefined,
    placeholder: 'Seleccionar fecha',
    disabled: false,
    clearable: true,
    minDate: undefined,
    maxDate: undefined,
  },
);

const emit = defineEmits<{
  'update:modelValue': [value: string | null];
}>();

const dateValue = computed({
  get: () => props.modelValue,
  set: (value: unknown) => {
    emit('update:modelValue', typeof value === 'string' ? value : null);
  },
});
</script>

<template>
  <div class="app-date-picker space-y-2.5">
    <div v-if="props.label" class="flex min-h-5 items-center">
      <label :for="props.id" class="text-sm font-medium text-(--app-color-label)">
        {{ props.label }}
      </label>
    </div>

    <VueDatePicker
      :id="props.id"
      v-model="dateValue"
      class="w-full"
      model-type="yyyy-MM-dd"
      :formats="{ input: 'yyyy-MM-dd' }"
      :teleport="true"
      :enable-time-picker="false"
      :auto-apply="true"
      :placeholder="props.placeholder"
      :disabled="props.disabled"
      :clearable="props.clearable"
      :min-date="props.minDate"
      :max-date="props.maxDate"
      text-input
      v-bind="$attrs"
    >
      <template #input-icon>
        <CalendarDaysIcon class="h-4 w-4 text-(--app-color-text-subtle)" />
      </template>

      <template #clear-icon="{ clear }">
        <button
          type="button"
          class="flex items-center text-(--app-color-text-subtle) transition hover:text-(--app-color-text)"
          aria-label="Limpiar fecha"
          @click.prevent="clear"
        >
          <XMarkIcon class="h-4 w-4" />
        </button>
      </template>
    </VueDatePicker>
  </div>
</template>

<style scoped>
.app-date-picker {
  --dp-font-family: inherit;
  --dp-font-size: 0.875rem;
  --dp-border-radius: 0.5rem;
  --dp-cell-border-radius: 0.5rem;
  --dp-background-color: var(--app-color-input-bg);
  --dp-text-color: var(--app-color-input-text);
  --dp-hover-color: var(--app-color-surface-muted);
  --dp-hover-text-color: var(--app-color-text);
  --dp-hover-icon-color: var(--app-color-text);
  --dp-primary-color: var(--app-color-primary);
  --dp-primary-disabled-color: color-mix(
    in srgb,
    var(--app-color-primary) 45%,
    var(--app-color-surface)
  );
  --dp-primary-text-color: var(--app-color-primary-foreground);
  --dp-secondary-color: var(--app-color-text-subtle);
  --dp-border-color: var(--app-color-input-border);
  --dp-menu-border-color: var(--app-color-border);
  --dp-border-color-hover: var(--app-color-primary);
  --dp-border-color-focus: var(--app-color-primary);
  --dp-disabled-color: var(--app-color-surface-muted);
  --dp-disabled-color-text: var(--app-color-text-subtle);
  --dp-scroll-bar-background: var(--app-color-surface-muted);
  --dp-scroll-bar-color: var(--app-color-border-strong);
  --dp-success-color: var(--app-color-success);
  --dp-icon-color: var(--app-color-text-subtle);
  --dp-danger-color: var(--app-color-danger);
  --dp-marker-color: var(--app-color-danger);
  --dp-tooltip-color: var(--app-color-surface);
  --dp-placeholder-color: var(--app-color-input-placeholder);
  --dp-highlight-color: color-mix(in srgb, var(--app-color-primary) 10%, transparent);
  --dp-range-between-dates-background-color: var(--app-color-surface-muted);
  --dp-range-between-dates-text-color: var(--app-color-text);
  --dp-range-between-border-color: var(--app-color-border);
  --dp-loader: 5px solid var(--app-color-primary);
  --dp-input-padding: 0.75rem 2.75rem 0.75rem 2.5rem;
  --dp-input-icon-padding: 2.5rem;
  --dp-menu-padding: 0.5rem;
  --dp-action-row-padding: 0.75rem;
}

.app-date-picker :deep(.dp__main) {
  width: 100%;
}

.app-date-picker :deep(.dp__input) {
  min-height: 3rem;
  box-shadow: none;
  color: var(--app-color-input-text);
  background-color: var(--app-color-input-bg);
  border-color: var(--app-color-input-border);
}

.app-date-picker :deep(.dp__input::placeholder) {
  color: var(--app-color-input-placeholder);
  opacity: 1;
}

.app-date-picker :deep(.dp__input_icon) {
  inset-inline-start: 0.75rem;
}

.app-date-picker :deep(.dp__input_icons) {
  padding: 0;
  width: 1rem;
  height: 1rem;
}

.app-date-picker :deep(.dp--clear-btn) {
  inset-inline-end: 0.75rem;
}

.app-date-picker :deep(.dp__menu),
.app-date-picker :deep(.dp__overlay) {
  background-color: var(--app-color-surface);
  color: var(--app-color-text);
  border-color: var(--app-color-border);
}

.app-date-picker :deep(.dp__calendar_header),
.app-date-picker :deep(.dp__calendar_item),
.app-date-picker :deep(.dp__month_year_select),
.app-date-picker :deep(.dp--year-select),
.app-date-picker :deep(.dp__selection_preview),
.app-date-picker :deep(.dp__time_display),
.app-date-picker :deep(.dp__overlay_cell),
.app-date-picker :deep(.dp__tooltip_text) {
  color: var(--app-color-text);
}

.app-date-picker :deep(.dp__cell_offset),
.app-date-picker :deep(.dp__week_num),
.app-date-picker :deep(.dp__calendar_header_item),
.app-date-picker :deep(.dp__action_cancel),
.app-date-picker :deep(.dp__secondary-color) {
  color: var(--app-color-text-subtle);
}

.app-date-picker :deep(.dp__input:focus),
.app-date-picker :deep(.dp__input_focus) {
  box-shadow: 0 0 0 4px var(--app-color-focus-ring);
}

.app-date-picker :deep(.dp__menu) {
  box-shadow: var(--app-shadow-card);
}

.app-date-picker :deep(.dp__action_button) {
  font-size: 0.75rem;
}
</style>
