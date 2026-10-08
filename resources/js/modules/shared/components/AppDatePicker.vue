<script setup lang="ts">
import { CalendarDaysIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { computed } from 'vue';
import { VueDatePicker } from '@vuepic/vue-datepicker';
import { useThemeStore } from '@/stores/theme';

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
    enableTimePicker?: boolean;
    error?: string;
    minDate?: Date | string | number;
    maxDate?: Date | string | number;
  }>(),
  {
    modelValue: null,
    label: undefined,
    placeholder: 'Seleccionar fecha',
    disabled: false,
    clearable: true,
    enableTimePicker: false,
    error: undefined,
    minDate: undefined,
    maxDate: undefined,
  },
);

const emit = defineEmits<{
  'update:modelValue': [value: string | null];
  change: [value: string | null];
  blur: [event: FocusEvent];
}>();

const themeStore = useThemeStore();

const dateValue = computed({
  get: () => props.modelValue,
  set: (value: unknown) => {
    const normalizedValue = typeof value === 'string' ? value : null;

    emit('update:modelValue', normalizedValue);
    emit('change', normalizedValue);
  },
});

const datePickerUi = computed(() => ({
  input: 'app-date-picker__input',
  menu: props.enableTimePicker
    ? 'app-date-picker__menu app-date-picker__menu--time-enabled'
    : 'app-date-picker__menu app-date-picker__menu--time-disabled',
  calendar: 'app-date-picker__calendar',
}));

function handleBlur(event?: FocusEvent): void {
  emit('blur', event ?? new FocusEvent('blur'));
}
</script>

<template>
  <div class="app-date-picker space-y-2.5">
    <div v-if="props.label" class="flex min-h-5 items-center">
      <label
        :for="props.id"
        class="text-sm font-medium"
        :class="props.error ? 'text-(--app-color-danger)' : 'text-(--app-color-label)'"
      >
        {{ props.label }}
      </label>
    </div>

    <VueDatePicker
      :id="props.id"
      v-model="dateValue"
      class="w-full"
      :ui="datePickerUi"
      :dark="themeStore.isDarkMode"
      model-type="yyyy-MM-dd"
      :formats="{ input: 'yyyy-MM-dd' }"
      :teleport="true"
      :time-picker="props.enableTimePicker"
      :auto-apply="true"
      :placeholder="props.placeholder"
      :disabled="props.disabled"
      :clearable="props.clearable"
      :min-date="props.minDate"
      :max-date="props.maxDate"
      :aria-invalid="Boolean(props.error)"
      :class="props.error ? 'app-date-picker--error' : ''"
      text-input
      v-bind="$attrs"
      @blur="handleBlur"
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

    <p v-if="props.error" class="text-sm text-(--app-color-danger)">
      {{ props.error }}
    </p>
  </div>
</template>

<style>
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

.app-date-picker .dp__main {
  width: 100%;
}

.app-date-picker .dp__input,
.app-date-picker .app-date-picker__input {
  min-height: 3rem;
  box-shadow: none;
  color: var(--app-color-input-text);
  background-color: var(--app-color-input-bg);
  border-color: var(--app-color-input-border);
}

.app-date-picker .dp__input::placeholder,
.app-date-picker .app-date-picker__input::placeholder {
  color: var(--app-color-input-placeholder);
  opacity: 1;
}

.app-date-picker .dp__input_icon {
  inset-inline-start: 0.75rem;
}

.app-date-picker .dp__input_icons {
  padding: 0;
  width: 1rem;
  height: 1rem;
}

.app-date-picker .dp--clear-btn {
  inset-inline-end: 0.75rem;
}

.app-date-picker__menu,
.app-date-picker__menu .dp__overlay {
  background-color: var(--app-color-surface-muted);
  color: var(--app-color-text);
  border-color: var(--app-color-border);
}

.app-date-picker__menu .dp__menu_inner,
.app-date-picker__menu .dp__calendar,
.app-date-picker__menu .dp__month_year_row,
.app-date-picker__menu .dp__action_row,
.app-date-picker__calendar {
  background-color: var(--app-color-surface-muted);
}

.app-date-picker__menu .dp__calendar_header,
.app-date-picker__menu .dp__calendar_item,
.app-date-picker__menu .dp__month_year_select,
.app-date-picker__menu .dp--year-select,
.app-date-picker__menu .dp__selection_preview,
.app-date-picker__menu .dp__time_display,
.app-date-picker__menu .dp__overlay_cell,
.app-date-picker__menu .dp__tooltip_text,
.app-date-picker__menu .dp__action_cancel {
  color: var(--app-color-text);
}

.app-date-picker__menu .dp__cell_offset,
.app-date-picker__menu .dp__week_num,
.app-date-picker__menu .dp__calendar_header_item {
  color: var(--app-color-text-subtle);
}

.app-date-picker__menu .dp__today {
  border-color: var(--app-color-primary);
  color: var(--app-color-primary);
}

.app-date-picker__menu .dp__active_date,
.app-date-picker__menu .dp__range_start,
.app-date-picker__menu .dp__range_end,
.app-date-picker__menu .dp__action_buttons .dp__action_select {
  background-color: var(--app-color-primary);
  color: var(--app-color-primary-foreground);
}

.app-date-picker__menu .dp__range_between {
  background-color: color-mix(in srgb, var(--app-color-primary) 10%, transparent);
  border-color: color-mix(in srgb, var(--app-color-primary) 12%, transparent);
  color: var(--app-color-text);
}

.app-date-picker .dp__input:focus,
.app-date-picker .dp__input_focus,
.app-date-picker .app-date-picker__input:focus {
  box-shadow: 0 0 0 4px var(--app-color-focus-ring);
}

.app-date-picker--error .dp__input,
.app-date-picker--error .app-date-picker__input {
  border-color: var(--app-color-danger);
}

.app-date-picker__menu {
  box-shadow: var(--app-shadow-card);
}

.app-date-picker__menu .dp__action_button {
  font-size: 0.75rem;
}

.app-date-picker__menu--time-disabled .dp__tp-wrap,
.app-date-picker__menu--time-disabled .dp__time_picker_inline_container,
.app-date-picker__menu--time-disabled .dp__time_picker_overlay_container,
.app-date-picker__menu--time-disabled [data-test-id='open-time-picker-btn'],
.app-date-picker__menu--time-disabled [data-test-id='close-time-picker-btn'] {
  display: none;
}
</style>
